
let valueJson_ws_form=[];
let valueJson_ws_messages = [];
let valueJson_ws_setting = []
let state_seting_emsFormBuilder=false;
let poster_emsFormBuilder ='';

jQuery (function() {  
    //ajax_object.ajax_url ایجکس ادمین برای برگرداند مقدار لازم می شود
    //ajax_object.ajax_value مقدار جی سون
    //ajax_object.language زبان بر می گرداند
    //ajax_object.messages_state پیام های خوانده نشده را بر می گرداند

 //   console.log(ajax_object.ajax_value);
  valueJson_ws_form=ajax_object.ajax_value;
  poster_emsFormBuilder =ajax_object.poster
  console.log(`poster_emsFormBuilder`,poster_emsFormBuilder)
  fun_emsFormBuilder_render_view(5);
});

let count_row_emsFormBuilder =0;


// تابع نمایش فرم اصلی
function fun_emsFormBuilder_render_view(x){
  //console.log("value of valueJson_ws_form",valueJson_ws_form ,x)
  // if(typeof val==="object") valueJson_ws_form=val;
  // مقدار بصورت آبجکت گرفته شود ودر 
  //valueJson_ws_form 
  // نوشته شود

 let rows =""
 count_row_emsFormBuilder =x;
 let count =0;
 fun_backButton(2);
 if(valueJson_ws_form.length>0){
   for(let i  of valueJson_ws_form ){
    
     if(x>count){
       console.log(i.form_id)
       let newM=false;
       for(let ims of ajax_object.messages_state){
         console.log(`ajax_object return` ,ims)
         if(ims.form_id==i.form_id){
          newM=true;
         }
         console.log(`ajax_object return` ,ims , newM , i.form_id)
       }
       rows += `<tr class="" id="emsFormBuilder-tr-${i.form_id}" >                    
       <th scope="row" class="emsFormBuilder-tr" data-id="${i.form_id}">${Number(i.form_id)}</th>
       <td class="emsFormBuilder-tr" data-id="${i.form_id}">Form ${i.form_name}</td>
       <td class="emsFormBuilder-tr" data-id="${i.form_id}">${i.form_create_date}</td>
       <td > 
       <button type="button" class="btn btn-danger" onClick ="emsFormBuilder_delete(${i.form_id})">X</button>
       <button type="button" class="btn btn-secondary" onClick="emsFormBuilder_get_edit_form(${i.form_id})"  data-id="${i.form_id}"><i class="fa fa-pencil" aria-hidden="true"></i></button>
       <button type="button" class="btn btn-info" onClick="emsFormBuilder_messages(${i.form_id})"><i class=" ${newM==true ? ' fas fa-comment faa-bounce animated ': 'fa fa-comment-o'}" placeholder="preview"></i></button>
       </td>                               
       </tr>` ;
      count +=1;
     }
   }

   if (valueJson_ws_form.length<x) {document.getElementById("more_emsFormBuilder").style.display = "none";
  }

 
   document.getElementById('emsFormBuilder-content').innerHTML=`<div class="col-md-12  d-flex mat-shadow">
   <table class="table table-hover justify-content-center" id="emsFormBuilder-list">
        <thead>
            <tr >
            <th scope="col">#</th>
            <th scope="col">Form Name</th>
            <th scope="col">Create Date</th>
            <th scope="col">Edit</th>
            </tr>
        </thead>
        <tbody>
        ${rows}
        </tbody>
    </table>
 </div>
 `
 
 }else{
  fun_backButton(1);
  document.getElementById('emsFormBuilder-content').innerHTML=`<div class="col-md-12  d-flex mat-shadow">
  <div class="m-5 p-1 col text-center"> <a type="button" class="btn btn-info" href="admin.php?page=Emsfb_create" ><i class="fa fa-plus" placeholder="preview"></i> Create Form</a> </div>
  </div>`
 }
 



  for (const el of document.querySelectorAll(`.emsFormBuilder-tr`)){
    
    el.addEventListener("click", (e) => {
      emsFormBuilder_messages(el.dataset.id)
 

      
    });
  }
}


function emsFormBuilder_waiting_response(){
  document.getElementById('emsFormBuilder-list').innerHTML = `<div class=" d-flex justify-content-center align-items-center mt-3" id="emsFormBuilder_waiting_response"><h1 class="fas fa-sync fa-spin text-primary emsFormBuilder "></h1></div>`
}


function emsFormBuilder_get_edit_form(id){
console.log('id' ,id , typeof id)
  //fun_backButton()
  fun_backButton();
  emsFormBuilder_waiting_response();
  fun_get_form_by_id(id);
}


// نمایش پنجره پیغام حذف یک ردیف  فرم
function emsFormBuilder_delete (id){
  console.log(`show message do you want delete ? ${id}`);
  // پنجره مطمئن هستی می خوای فرم پاک کنی نمایش بده 

  document.getElementById('wpwrap').innerHTML+=`
  <div class=" overpage preview-overpage" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark">
    <h5 class="card-title text-white"><i class="fas fa-trash"></i> Remove!</h5>
    <br>
      <h4 class="text-white"> Are you sure you want to delete this item??</h4>
    <br>
    <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">NO</button>
    <button class="btn btn-danger" onclick=" fun_confirm_remove_emsFormBuilder(${Number(id)})">Yes</button>
  </div>
  <div>
</div></div></div>`;
window.scrollTo({ top: 0, behavior: 'smooth' });
}

function emsFormBuilder_popUp_message (title,message){
  // این پنجره برای نمایش پیام های عمومی است
  document.getElementById('wpwrap').innerHTML+=`
  <div class=" overpage preview-overpage" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark">
    <h5 class="card-title text-white"><i class="fa fa-bell-o "></i> ${title}</h5>
    <br>
      <h4 class="text-white">${message}</h4>
    <br>
    <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">Close</button>
  </div>
  <div>
</div></div></div>`;
window.scrollTo({ top: 0, behavior: 'smooth' });
}
function emsFormBuilder_popUp_loading (){
  // این پنجره برای نمایش پیام های عمومی است
  document.getElementById('wpwrap').innerHTML+=`
  <div class=" overpage preview-overpage" id="overpage">
  <div class="overpage-mbox">
  <div class="card-body m-13 bg-dark text-center">

    <br>
    <h1 class="fas fa-sync fa-spin text-primary emsFormBuilder mb-4"></h1>
     <h3 class="text-white">Please Waiting<h3>
    <br>
  </div>
  <div>
</div></div></div>`;
window.scrollTo({ top: 0, behavior: 'smooth' });
}

function emsFormBuilder_show_content_message (id){
  // پنجره نمایش فرم ثبت شده کاربر
  console.log(`show message`,id ,valueJson_ws_messages);
  const indx =valueJson_ws_messages.findIndex(x => x.msg_id === id.toString());
  const msg_id =valueJson_ws_messages[indx].msg_id;
  console.log(valueJson_ws_messages[indx],msg_id);

  const userIp = valueJson_ws_messages[indx].ip;
  const track = valueJson_ws_messages[indx].track;
  const date =valueJson_ws_messages[indx].date;
  
  const content =JSON.parse(valueJson_ws_messages[indx].content.replace(/[\\]/g, ''));
  
  const by = valueJson_ws_messages[indx].read_by!==null ? valueJson_ws_messages[indx].read_by : "Unkown"
  const m = fun_emsFormBuilder_show_messages(content,by, userIp ,track,date)
  //replay message ui
  let replayM = `<div class="mx-2 mt-2"><div class="form-group mb-1" id="replay_section__emsFormBuilder">
  <label for="replayM_emsFormBuilder">Replay:</label>
  <textarea class="form-control" id="replayM_emsFormBuilder" rows="3" data-id="${msg_id}"></textarea>
  </div>
  <div class="col text-right row">
  <button type="submit" class="btn btn-info" id="replayB_emsFormBuilder" OnClick="fun_send_replayMessage_emsFormBuilder(${msg_id})">Replay</button>
  <p class="mx-2" id="replay_state__emsFormBuilder">  </p>
  </div></div>
  `


  document.getElementById('wpwrap').innerHTML+=`
  <div class=" overpage preview-overpage" id="overpage">
  <div class="overpage-mbox bg-light">
  <div class="card-body m-13">
    <div class="card-title bg-secondary px-2 py-2 text-white m-0"><i class="fa fa-comments"></i> Message</div>
   
    <div class="my-2">
    <div class="my-1 mx-1 border border-secondary rounded-sm pb-3">
    
     <div class="mx-4 my-1 border-bottom border-info pb-1" id="conver_emsFormBuilder">
     
      <div id="loading_message_emsFormBuilder" class="efb-color text-center"><i class="fas fa-spinner fa-pulse"></i> loading...</div>
     </br>
      ${m} 
     </div>
     ${replayM}
     </div>
      
     </div>
   
    <button class="btn btn-primary" onclick=" close_overpage_emsFormBuilder(1)">Close</button>
  </div>
  <div>
</div></div></div>`;


window.scrollTo({ top: 0, behavior: 'smooth' });

}


// نمایش و عدم نمایش دکمه های صفحه اصلی
function fun_backButton(state){
  console.log(`fun_backButton` , document.getElementById("more_emsFormBuilder").style.display ,state)
 

  if(document.getElementById("more_emsFormBuilder").style.display == "block" && state==1  ){ 
    document.getElementById("more_emsFormBuilder").style.display = "none";
    console.log(document.getElementById("more_emsFormBuilder").style.display ,255)
    }else{
      document.getElementById("more_emsFormBuilder").style.display = "block" ;
    }


    if(state==0 || state==null){
      document.getElementById("more_emsFormBuilder").style.display = "none" ;
    }

    if(state==2){
      document.getElementById("more_emsFormBuilder").style.display = "block" ;
    }
}

// تابع بستن پنجره اورپیج
function close_overpage_emsFormBuilder(i) {
  document.getElementById('overpage').remove();
 // if (i==1) previewemsFormBuilder=false;
}


// حذف یک ردیف از جدول نمایشی
function fun_confirm_remove_emsFormBuilder(id){
  console.log(id, typeof id);

  
  // ای دی از جی سون پیدا شود حذف شود و به سمت سرور پیام حذف ارسال شود
  // صفحه رفرش شود
  fun_delete_form_with_id_by_server(parseInt(id));

  //کد زیر حذف نشود
  const foundIndex = Object.keys(valueJson_ws_form).length > 0 ? valueJson_ws_form.findIndex(x => x.form_id== id) : -1
  if(foundIndex!=-1) valueJson_ws_form.splice(foundIndex, 1);
  fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
  close_overpage_emsFormBuilder();
  //پایان عدم حذف 


}


// دکمه بازگشت و نمایش لیست اصلی
function fun_emsFormBuilder_back(){
 fun_emsFormBuilder_render_view(count_row_emsFormBuilder);

  
}


function fun_emsFormBuilder_show_messages(content,by,userIp,track,date){
  console.log(content,by,userIp,track,date);
  console.log(`by[${by}]userIp[${userIp}] , track[${track}]`)
  if (by ==1) {by='Admin'}else if(by==0 ||by.length==0 || by.length==-1 )(by="Guest")
  let m =`<Div class="border border-light round  p-2"><div class="border-bottom mb-1 pb-1">
   <span class="small"><b>Info:</b></span></br>
   <span class="small">By: ${by}</span></br>
   <span class="small">IP: ${userIp}</span></br>
  ${track!=0 ? `<span> Track No: ${track} </span></br>` :''}
  <span> Date: ${date} </span></small>
  </div>
  <div class="mx-1">
  <h6 class="my-3"> Response: </h6>`;
  for (const c of content){
    let value = `<b>${c.value}</b>`;
    console.log(`value up ${value}`)    ;
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
          value = type !=='avi' ? `</br><div class="px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="text-center" ><a href="${c.url}">Video Download Link</a></p>` :`<p class="text-center"><a href="${c.url}">Download Viedo</a></p>`;
        }else{
          value=`<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
     }else{
      console.log(c.url ,c.url.length)
      value =`</br><a class="btn btn-primary" href="${c.url}">${c.name}</a>`
    }
    }
    
    m +=`<p class="my-0">${c.name}: <span class="mb-1"> ${value!=='<b>@file@</b>'?value:''}</span> </p> `
  }
  m+= '</div></div>';
console.log(`m`,m)
  return m;
}


// دکمه نمایش بیشتر لیست اصلی
function fun_emsFormBuilder_more(){
count_row_emsFormBuilder +=5;
fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })

}

// تابع نمایش ویرایش فرم
function fun_ws_show_edit_form(id){
  document.getElementById('emsFormBuilder-content').innerHTML=`<div class="col-md-12 ">
  <div id="emsFormBuilder-form" >
  <form id="emsFormBuilder-form-id">
      <h1 id="emsFormBuilder-form-title">Form Bulider</h1>
      
      <div class="all-steps" id="all-steps"> 
          <span class="step"><i class="fa fa-tachometer"></i></span> 
          <span class="step"><i class="fa fa-briefcase"></i></span> 
          <div class="addStep" id="addStep" >
          </div>
          <span class="step"><i class="fa fa-floppy-o"></i></span> 
      </div>
      <div class="all-steps" > 
          <h5 class="step-name f-setp-name" id ="step-name">Define</h5> 
      </div>
      <div id="message-area"></div>
      <div class="tab" id="firsTab">
          <h5>Form Name:*</h5>
          <input placeholder="" type="text"  name="setps" class="require emsFormBuilder" id="form_name" max="20">
          </br>
          <h5>Number of steps:*</h5>
          <input placeholder="1,2,3.." type="number"  name="setps" class="require emsFormBuilder" id="steps" max="20">
      </div>
      <div class="tab" id="tabInfo">

      </div>
      <div  id="tabList">

      </div>
 
      <div class="thanks-message text-center" id="emsFormBuilder-text-message-view"> 
          <h3>Done</h3> <span>Great, Your form is builded successfully</span>
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
form_ID_emsFormBuilder=id;
  run_code_ws_1();
  run_code_ws_2();
  fun_render_view_core_emsFormBuilder();
 
}


function fun_send_replayMessage_emsFormBuilder(id){
  //پاسخ مدیر را ارسال می کند به سرور 
  
  
  const message = document.getElementById('replayM_emsFormBuilder').value.replace(/\n/g,'</br>');		
  console.log(message,id)
  document.getElementById('replay_state__emsFormBuilder').innerHTML=`<i class="fas fa-spinner fa-pulse"></i> Sending...`;
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  // +='disabled fas fa-spinner fa-pulse';
  const ob = [{name:'Message',value:message ,by:ajax_object.user_name}];
  console.log(ob);
  let isHTML = RegExp.prototype.test.bind(/(<([^>]+)>)/i);
  if (message.length<1 || isHTML(message)){
    document.getElementById('replay_state__emsFormBuilder').innerHTML=`<h6><i class="fas fa-exclamation-triangle faa-flash animated text-danger"></i> Error , You can't use HTML Tag or send blanket message.</h6>`;
    return 
  }
  fun_send_replayMessage_ajax_emsFormBuilder(ob ,id)


}


function fun_ws_show_list_messages(value){
  //لیست مخاطبانی که یک فرم را پر کرده اند نمایش می دهد
 let rows ='';
 let no=1;
  for(const v of value ){
    const state =  Number(v.read_);
    rows += `<tr class="emsFormBuilder-tr" id="" onClick="fun_open_message_emsFormBuilder(${v.msg_id} , ${state})">                    
      <th scope="row" class="">${no}</th>
      <td class="" >${v.track}</td>
      <td class="">${v.date}</td>
      <td> 
      <button type="button" class="btn btn-info" ><i id="icon-${v.msg_id}"class="fa ${Number(v.read_)==0 ?'fa-envelope faa-bounce animated':'fa-envelope-open-o'} " aria-hidden="true"></i></button>
      </td>                               
      </tr>` ;
      no +=1;
  }
  document.getElementById('emsFormBuilder-content').innerHTML=`<div class="col-md-12  d-flex mat-shadow">
  <table class="table table-hover justify-content-center" id="emsFormBuilder-list">
       <thead>
           <tr >
           <th scope="col">#</th>
           <th scope="col">Track No.</th>
           <th scope="col">Form Date</th>
           <th scope="col">Content</th>
           </tr>
       </thead>
       <tbody>
       ${rows}
       </tbody>
   </table>
</div>
  `;

 
}


function fun_delete_form_with_id_by_server(id){
  console.log(ajax_object.ajax_url ,id);
  $(function () {
    data = {
      action: "remove_id_Emsfb",
      type: "POST",
      id:id,
      nonce:ajax_object_core.nonce,     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      console.log(res);
      if (res.success==true) {

      }else{
        console.log(res);
      }
    })
  });

}




function emsFormBuilder_messages(id){
  console.log(id);
  fun_get_messages_by_id(Number(id));
  emsFormBuilder_waiting_response();
  fun_backButton(0);
//fun_backButton(1);
}

function fun_open_message_emsFormBuilder(msg_id,state){
 // console.log(msg_id,state ,valueJson_ws_messages);
  fun_emsFormBuilder_get_all_response_by_id(Number(msg_id));
  emsFormBuilder_show_content_message(msg_id)
  if(state==0){
    fun_update_message_state_by_id(msg_id);
  }
}



function fun_get_form_by_id(id){
  $(function () {
    data = {
      action: "get_form_id_Emsfb",
      type: "POST",
      nonce:ajax_object_core.nonce,
      id:id     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      if (res.success==true) {
        console.log(res.data.ajax_value ,res);
        const value =JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
        localStorage.setItem('valueJson_ws_p',JSON.stringify(value) );
        const edit ={id:res.data.id, edit:true};
        localStorage.setItem('Edit_ws_form',JSON.stringify(edit) )
        fun_ws_show_edit_form(id)
      }else{
        console.log(res);
      }
    })
  });
}
function fun_update_message_state_by_id(id){
  $(function () {
    data = {
      action: "update_message_state_Emsfb",
      type: "POST",
      nonce:ajax_object_core.nonce,
      id:id     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      if (res.success==true) {
        console.log(res);
        document.getElementById(`icon-${id}`).className=`fa fa-envelope-open-o `;
       /*  console.log(res.data.ajax_value ,res);
        const value =JSON.parse(res.data.ajax_value.replace(/[\\]/g, ''));
        localStorage.setItem('valueJson_ws_p',JSON.stringify(value) );
        const edit ={id:res.data.id, edit:true};
        localStorage.setItem('Edit_ws_form',JSON.stringify(edit) )
        fun_ws_show_edit_form(id) */
      }else{
        console.log(res);
      }
    })
  });
}
function fun_get_messages_by_id(id){
  console.log(`fun_get_messages_by_id(${id})` ,ajax_object.ajax_url)
  $(function () {
    data = {
      action: "get_messages_id_Emsfb",
      nonce:ajax_object_core.nonce,
      type: "POST",
      id:id     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      console.log(`messages`,res);
      if (res.success==true) {
        valueJson_ws_messages =res.data.ajax_value;
        localStorage.setItem('valueJson_ws_messages',JSON.stringify(valueJson_ws_messages) );
        console.log(`resp`,res);
        fun_ws_show_list_messages(valueJson_ws_messages) 
      }else{
        console.log(res);
      }
    })
  });
}
function fun_emsFormBuilder_get_all_response_by_id(id){
  console.log(`fun_emsFormBuilder_get_all_response_by_id(${id})` ,ajax_object.ajax_url)
  $(function () {
    data = {
      action: "get_all_response_id_Emsfb",
      nonce:ajax_object_core.nonce,
      type: "POST",
      id:id     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      console.log(`messages`,res);
      if (res.success==true) {
        
       // localStorage.setItem('valueJson_ws_messages',JSON.stringify(valueJson_ws_messages) );
        console.log(`get_all_response_id_Emsfb`,res);
        fun_ws_show_response(res.data.ajax_value) 
      }else{
        console.log(res);
      }
    })
  });
}



function fun_send_replayMessage_ajax_emsFormBuilder(message,id){
  console.log(`fun_send_replayMessage_ajax_emsFormBuilder(${id})` ,message ,ajax_object.ajax_url)
  if(message.length<1){
    document.getElementById('replay_state__emsFormBuilder').innerHTML="Please Enter message";
    document.getElementById('replayM_emsFormBuilder').innerHTML="";
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    return;
  }
  
  $(function () {
    data = {
      action: "set_replyMessage_id_Emsfb",
      type: "POST",
      nonce:ajax_object_core.nonce,
      id:id,
      message: JSON.stringify(message)     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      if (res.success==true) {
        console.log(`response`,res);
        document.getElementById('replay_state__emsFormBuilder').innerHTML=res.data.m;
        document.getElementById('replayM_emsFormBuilder').innerHTML="";
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');

        // اضافه شدن به سمت یو آی 
        const userIp =ajax_object.user_ip;
        const date = Date();
        console.log(message,"content" ,message.by);
        document.getElementById('replayM_emsFormBuilder').value="";
        
        fun_emsFormBuilder__add_a_response_to_messages(message,message[0].by,ajax_object.user_ip,0,date);
   
      }else{
        console.log(res);
        document.getElementById('replay_state__emsFormBuilder').innerHTML=res.data.m;
        document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
      }
    })
  });
}


function fun_emsFormBuilder__add_a_response_to_messages(message,by,userIp,track,date){
  
  console.log('592',message,`by[${by}]userIp[${userIp}]track[${track}]date[${date}]`);
  document.getElementById('conver_emsFormBuilder').innerHTML+= fun_emsFormBuilder_show_messages(message,by,userIp,track,date);
}


function fun_ws_show_response(value){
  console.log("598",value)
  for (v of value){
    console.log(v.content);
    const content =v.content ? JSON.parse(v.content.replace(/[\\]/g, '')) : {name:'Message', value:'message note exists'}
    fun_emsFormBuilder__add_a_response_to_messages(content,v.rsp_by,v.ip,0,v.date);
  }
  document.getElementById('loading_message_emsFormBuilder').remove();
}


function fun_show_content_page_emsFormBuilder(state){
  console.log(state);
  if(state=="forms"){
   /*  if( state_seting_emsFormBuilder!=true){
      fun_emsFormBuilder_render_view(count_row_emsFormBuilder);
      if (valueJson_ws_form.length>count_row_emsFormBuilder) fun_backButton(2);
      state=1;
    }else{ */
      window.location.reload();
      document.getElementById('emsFormBuilder-content').innerHTML=`<h2 id="loading_message_emsFormBuilder" class="efb-color text-center m-5 center"><i class="fas fa-spinner fa-pulse"></i>Loading</h2>`
    //}

  }else if(state=="setting"){
    fun_show_setting__emsFormBuilder();
    fun_backButton(0);
    state=2
  }else if(state=="help"){
    fun_show_help__emsFormBuilder();
    state=4
  }
  fun_hande_active_page_emsFormBuilder(state);
}

function fun_hande_active_page_emsFormBuilder(no){
  //active
  let count=0;
  for (const el of document.querySelectorAll(`.nav-link`)){
  count +=1;
  console.log('nav-link',count , no)
   if(el.classList.contains('active')) el.classList.remove('active');
    if(count==no )el.classList.add('active');
   //active
  }
}

function fun_show_help__emsFormBuilder(){
  document.getElementById("more_emsFormBuilder").style.display = "none"
    listOfHow_emsfb ={
     /*  1:{title:'How to activate pro version of Easy form builder.',url:'https://www.youtube.com/embed/RZTyFcjZTSM'},*/
      2:{title:'How to config Easy form Builder.',url:'https://www.youtube.com/embed/DEQNHMPT0rQ'},
      3:{title:'How to get google re-captcha and add to Easy Form Builder.',url:'https://www.youtube.com/embed/a1jbMqunzkQ'},
      4:{title:'How to activate the alert email of a new response.',url:'https://www.youtube.com/embed/So2RAzu-OHU'},
      5:{title:'How to Create a Form with Easy form Builder.',url:'https://www.youtube.com/embed/7jS01CEtbDg'},
      6:{title:'How to activation Tracking Code in Easy form Builder.',url:'https://www.youtube.com/embed/im3aKby4E14'},
      7:{title:'How to work with panel of Easy form Builder.',url:'https://www.youtube.com/embed/7jS01CEtbDg'},
      8:{title:'How to Add tracking Form to a post or page.',url:'https://www.youtube.com/embed/c1_gCFihrH8'},
      9:{title:'How to find a response by tracking code.',url:'https://www.youtube.com/embed/vqKi9BJbO7k'}
   /*    10:{title:'How to test Pro version on localhost',url:'https://www.youtube.com/embed/RZTyFcjZTSM'}, */
    }


  let str ="";
  for(const l in listOfHow_emsfb){
      console.log(l);
  str +=`
  <div class="m-1">
  <div class=" bg-info " >
  <button id="heading${l}" class=" btn-block card-header btn bg-info text-white" data-toggle="collapse" data-target="#collapse${l}" aria-expanded="true" aria-controls="collapseOne">
    <h6 class="mb-0 ">
      ${listOfHow_emsfb[l].title}
      </h6>
      </button>
  </div>

  <div id="collapse${l}" class="collapse ${l==0?' show':''}" aria-labelledby="heading${l}" data-parent="#accordion">
    <div class="card-body align-self-center">
      <iframe width="560" height="315" src="${listOfHow_emsfb[l].url}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  </div>
  </div>
  `
  }
  document.getElementById('emsFormBuilder-content').innerHTML=`<div id="accordion" class="m-5">${str}
  </div>`;

}
function fun_show_setting__emsFormBuilder(){
 // console.log( 610,ajax_object.setting);
 let activeCode = 'null';
 let sitekey = 'null';
 let secretkey = 'null';
 let email = 'null';
 let trackingcode ='null';
 console.log(`valueJson_ws_setting ${valueJson_ws_setting.length}`)
 if((ajax_object.setting[0] && ajax_object.setting[0].setting.length>5) || typeof valueJson_ws_setting=="object" && valueJson_ws_setting.length!=0 ){

  // اضافه کردن تنظیمات
 
 if(valueJson_ws_setting.length==0) valueJson_ws_setting= JSON.parse(ajax_object.setting[0].setting.replace(/[\\]/g, ''));
  console.log(`setting`,valueJson_ws_setting)
  const f= (name)=>{
    console.log('valueJson_ws_setting[name]', valueJson_ws_setting[name])
    if(valueJson_ws_setting[name]){
      console.log(name, valueJson_ws_setting[name]);
      return valueJson_ws_setting[name] 
    }else{
      console.log(name, valueJson_ws_setting[name]);
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
  console.log(`activeCode[${activeCode}] sitekey[${sitekey}] secretkey[${secretkey}] email[${email}] trackingcode[${trackingcode}]`);

  document.getElementById('emsFormBuilder-content').innerHTML=`  <div id="setting_emsFormBuilder" class="mx-auto border border-primary">
 
    <div class="py-2 pb-5 bg-light">
      <h6 class="border-bottom border-info mx-3 mt-2 text-info font-weight-bold" aria-describedby="UnlitedVersionHelp">Pro Version  <h6>
       <small id="UnlitedVersionHelp" class="form-text text-muted mx-3 mb-3"><a href="${proUrl_ws}">Click here to get Activate Code.</a>  </small>
      <div class="form-group mx-5">
        <label for="activeCode_emsFormBuilder"Activate Code</label>
        <input type="text" class="form-control" id="activeCode_emsFormBuilder" placeholder="Enter Activate Code" ${activeCode!=="null" ? `value="${activeCode}"` :"" }>             
      </div>
    </div>
   <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2" aria-describedby="reCAPTCHAHelp"> reCAPTCHA v2 <h6>
       <small id="reCAPTCHAHelp" class="form-text text-muted mx-3 mb-3"><a href="https://www.google.com/recaptcha/about/">reCAPTCHA</a> protects your website from fraud and abuse.<a href="">Click here to watch a video tutorial.</a></small>
      <div class="form-group mx-5">
        <label for="sitekey_emsFormBuilder">SITE KEY</label>
        <input type="text" class="form-control" id="sitekey_emsFormBuilder" placeholder="Enter SITE KEY" ${sitekey!=="null" ? `value="${sitekey}"` :"" }>
       
      </div>
      <div class="form-group  mx-5">
        <label for="secretkey_emsFormBuilder">SECRET KEY</label>
        <input type="text" class="form-control" id="secretkey_emsFormBuilder" placeholder="Enter SECRET KEY" ${secretkey!=="null" ? `value="${secretkey}"` :"" }>
      </div>
    </div>

    <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2" aria-describedby="AlertEmailHelp"> Alert Email <h6>
       <small id="AlertEmailHelp" class="form-text text-muted mx-3 mb-3">When <b>Easy Form Builder</b> recives a new message, It will send an alret email to admin of plugin.</small>
      <div class="form-group mx-5" id="email_emsFormBuilder-row">
        <label for="email_emsFormBuilder">Email</label>
        <input type="email" class="form-control" id="email_emsFormBuilder" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" placeholder="Enter Admin Email" ${email!=="null" ? `value="${email}"` :"" }>             
      </div>
    </div>
    <div class="py-2">
    <h6 class="border-bottom border-info mx-3 mt-2 " aria-describedby="filesCelar">Clear Files<h6>
    <small id="filesCelar" class="form-text text-muted mx-3 mb-3">You can Remove unnecessary file uploaded by user with below button</small>
    <div class="form-group mx-5">
    <a  class="btn btn btn-secondary" OnClick="clear_garbeg_emsFormBuilder()">Clear unnecessary files</a>          
    </div>
    <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2 " aria-describedby="TrackingCodeHelp">Tracking code  <h6>
       <small id="TrackingCodeHelp" class="form-text text-muted mx-3 mb-3">If you don't want to show tracking code to user, don't mark below option. </small>
      <div class="form-group mx-5">
       <input type="checkbox" class="form-check-input" id="trackingcode_emsFormBuilder" ${trackingcode!=="null" && ( trackingcode=="true" ||  trackingcode===true)? `checked` :"" }>
  <label class="form-check-label" for="trackingcode_emsFormBuilder">Show tracking Code</label>       
      </div>
    </div>
    <div class="py-2">
      <h6 class="border-bottom border-info mx-3 mt-2 " aria-describedby="shortCodeHelp">Tracking code Finder <h6>
       <small id="shortCodeHelp" class="form-text text-muted mx-3 mb-3">Copy and Paste below short-code of tracking code finder in any page or post.<a href="">Click here to watch tutorial Video.</a>   </small>
      <div class="form-group mx-5">
      <input type="text" class="form-control" id="shortCode_emsFormBuilder" value="[EMS_Form_Builder_tracking_finder]" readonly>          
      </div>
    </div>

  </div>


<div class="m-2 row">
 <a type="submit" class="btn btn-primary" onClick="fun_set_setting_emsFormBuilder()" id="btn_set_setting_emsFormBuilder"><i class="fa fa-floppy-o" aria-hidden="true"></i> Save </a>
 <div id="loading_message_emsFormBuilder" class="efb-color text-center mx-2 invisible"><i class="fas fa-spinner fa-pulse"></i> Waiting...</div>
 </div>

</div>`
}


function fun_set_setting_emsFormBuilder(){
  console.log("fun_set_setting_emsFormBuilder");
  fun_state_loading_message_emsFormBuilder(1);
  fun_State_btn_set_setting_emsFormBuilder();
  const f = (id)=>{
    const el =document.getElementById(id)
    let r= "NotFoundEl"
    if(el.type =="text" || el.type =="email" ){
      return el.value;
    }else if(el.type =="checkbox"){
      return el.checked;
    }
    return "NotFoundEl"
  }
  const v = (id)=>{
    const el =document.getElementById(id);
    if(el.type!=="checkbox"){
      if(el.value.length<5 && id!=="activeCode_emsFormBuilder" && id!=="email_emsFormBuilder"){
        el.classList.add('invalid');
        window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
        return false;
      }else if(id=="activeCode_emsFormBuilder"){
          //برای برسی صحیح بودن کد امنیتی وارد شده
          if(el.value.length<5  &&  el.value.length!=0){
            el.classList.add('invalid');
            window.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
            return false;
          }
      }else{
        if(el.classList.contains("invalid")==true) el.classList.remove('invalid');
        if(el.type=="email" && el.value.length>0){          
          return  valid_email_emsFormBuilder(el);
        }
      }
    }
    return true;
  }
  const ids=['activeCode_emsFormBuilder','sitekey_emsFormBuilder','secretkey_emsFormBuilder','email_emsFormBuilder','trackingcode_emsFormBuilder'];
  let state= true
  for(id of ids){
    if(v(id)===false){
      state=false;
      fun_state_loading_message_emsFormBuilder(1);
      fun_State_btn_set_setting_emsFormBuilder();
      break;
    }
  }
  if(state==true){
    const activeCode = f('activeCode_emsFormBuilder');
    const sitekey = f(`sitekey_emsFormBuilder`);
    const secretkey = f(`secretkey_emsFormBuilder`);
    const email = f(`email_emsFormBuilder`);
    const trackingcode = f(`trackingcode_emsFormBuilder`);
    fun_send_setting_emsFormBuilder({activeCode:activeCode, siteKey:sitekey, secretKey:secretkey, emailSupporter:email, trackingCode:`${trackingcode}`  });
  }
}

function fun_State_btn_set_setting_emsFormBuilder(){
  if(document.getElementById('btn_set_setting_emsFormBuilder').classList.contains('disabled')==true){
    document.getElementById('btn_set_setting_emsFormBuilder').classList.remove('disabled');   
  }else{
    document.getElementById('btn_set_setting_emsFormBuilder').classList.add('disabled');
  }
}


function fun_state_loading_message_emsFormBuilder(state){
  //btn_set_setting_emsFormBuilder
  if(state!==0){
    if(document.getElementById('loading_message_emsFormBuilder').classList.contains('invisible')==true){
      document.getElementById('loading_message_emsFormBuilder').classList.remove('invisible');
      document.getElementById('loading_message_emsFormBuilder').classList.add('visible');
    }else{
      document.getElementById('loading_message_emsFormBuilder').classList.remove('visible');
      document.getElementById('loading_message_emsFormBuilder').classList.add('invisible');
    }
  }
}


function fun_send_setting_emsFormBuilder(data){
  console.log(data);
  //ارسال تنظیمات به ووردپرس
    $(function () {
    data = {
      action: "set_setting_Emsfb",
      type: "POST",
      nonce:ajax_object_core.nonce,
      message:data     
    };
    $.post(ajax_object.ajax_url, data, function (res) {
      console.log(`messages`,res);
      if (res.success==true) {
        console.log(`resp`,res);
        valueJson_ws_setting=data.message;    
        //console.log(` first lengi of valueJson_ws_setting [${valueJson_ws_setting.length}]` ,valueJson_ws_setting);    
        fun_show_setting__emsFormBuilder();
        if(res.data.success==true){      
          // اگر پاسخ  مست گرفت از سرور
        
          if (document.getElementById('setting_return_emsFormBuilder')==null){   
            state_seting_emsFormBuilder=true;
            document.getElementById('setting_emsFormBuilder').innerHTML +=`<div class="m-2 row" id="setting_return_emsFormBuilder"><div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="fas fa-thumbs-up faa-bounce animated "></i>Saved</div></div>`
          }else{
            state_seting_emsFormBuilder=true;
            document.getElementById('setting_return_emsFormBuilder').innerHTML =`<div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="fas fa-thumbs-up faa-bounce animated "></i> Saved</div>`
          }
        }else{
          console.log(res.data);
          if (document.getElementById('setting_return_emsFormBuilder')==null){ 
          document.getElementById('setting_emsFormBuilder').innerHTML +=`<div class="m-2 row" id="setting_return_emsFormBuilder"><div id="loading_message_emsFormBuilder" class="text-danger text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> Error , ${res.data.m}</div></div>`
          }else{
            document.getElementById('setting_return_emsFormBuilder').innerHTML =`<div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> Error , ${res.data.m}</div>`
          }
        }
      }else{
        console.log(res);
        if (document.getElementById('setting_return_emsFormBuilder')==null){ 
          document.getElementById('setting_emsFormBuilder').innerHTML +=`<div class="m-2 row" id="setting_return_emsFormBuilder"><div id="loading_message_emsFormBuilder" class="text-danger text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> Error Stng-400 , ${res}</div></div>`
          }else{
            document.getElementById('setting_return_emsFormBuilder').innerHTML =`<div id="loading_message_emsFormBuilder" class="text-info text-center mx-2"><i class="fas fa-exclamation-triangle faa-flash animated"></i> Error Stng-400, ${res}</div>`
          }
      }
    })
  });  
}


function fun_find_track_emsFormBuilder(){
  //function find track code
  const el =document.getElementById("track_code_emsFormBuilder").value;
  console.log(el);
  if (el.length!=12 ){
    emsFormBuilder_popUp_message("Error","Tracking Code is not valid.");
   
  }else{
    console.log('fun_find_track_emsFormBuilder',el  )
      document.getElementById('track_code_emsFormBuilder').disabled=true;
      document.getElementById('track_code_btn_emsFormBuilder').disabled=true;
      const btnValue = document.getElementById('track_code_btn_emsFormBuilder').innerHTML;
      document.getElementById('track_code_btn_emsFormBuilder').innerHTML=`<i class="fas fa-spinner fa-pulse"></i>`;
      console.log(btnValue);

   
     
        $(function () {     
          console.log('get_track_id_Emsfb');  
          data = {
            action: "get_track_id_Emsfb",
            nonce:ajax_object_core.nonce,
            value: el,          
          };
      
          $.post(ajax_object.ajax_url, data, function (res) {
          
             if (res.data.success==true) {
              valueJson_ws_messages =res.data.ajax_value;
              console.log(`valueJson_ws_messages`,valueJson_ws_messages);
              localStorage.setItem('valueJson_ws_messages',JSON.stringify(valueJson_ws_messages) );
              document.getElementById("more_emsFormBuilder").style.display = "none";
              fun_ws_show_list_messages(valueJson_ws_messages) 
              document.getElementById('track_code_emsFormBuilder').disabled=false;
              document.getElementById('track_code_btn_emsFormBuilder').disabled=false;
              document.getElementById('track_code_btn_emsFormBuilder').innerHTML=btnValue;
            } else {             
              emsFormBuilder_popUp_message("Erorr",res.data.m);
              document.getElementById('track_code_emsFormBuilder').disabled=false;
              document.getElementById('track_code_btn_emsFormBuilder').disabled=false;
              document.getElementById('track_code_btn_emsFormBuilder').innerHTML=btnValue
      
            } 
          })
        });       }
    
  
}//end function 


function clear_garbeg_emsFormBuilder(){
  emsFormBuilder_popUp_loading()
   $(function () {     
    console.log('clear_garbeg_emsFormBuilder');  
    data = {
      action: "clear_garbeg_Emsfb",
      nonce:ajax_object_core.nonce         
    };

    $.post(ajax_object.ajax_url, data, function (res) {
       close_overpage_emsFormBuilder(1)
       if (res.data.success==true) {
        emsFormBuilder_popUp_message("Done",res.data.m);
      } else {             
        emsFormBuilder_popUp_message("Error",res.data.m);

      } 
    })
  }); 
  
}


