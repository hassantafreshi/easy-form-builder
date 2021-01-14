console.log('server.js');


jQuery (function() {

    console.log(`server.js`,'ajax_s_esmf')
    
    if(server_whitestudio_news_state){
        
        const message = `<div class="alert alert-primary m-2" role="alert"> <b> 🎉 New Update 2🎉 ,</b>
        Easy Form Builder Version <span class="font-italic">${ajax_s_esmf.LeastVersion}</span> has published with new attribute , Please Update plugin. <a href="#" class="alert-link"> Click here</a>.
        </div>`;
        fun_show_update_server_message_emsFormBuilder(server_whitestudio_news_message)
    }

  
});


function fun_show_update_server_message_emsFormBuilder(message){
    console.log(`server.js` ,'fun_show_update_server_message_emsFormBuilder');
   
  const wpcontent = document.getElementById('wpbody-content');
  const newItem = document.createElement("DIV");
  newItem.id='id_usm_emsFormBuilder'
  wpcontent.insertBefore(newItem, wpcontent.firstChild);
  document.getElementById('id_usm_emsFormBuilder').innerHTML=message;
 // wpcontent.insertAdjacentElement('afterbegin', message)
}