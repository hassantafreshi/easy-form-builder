//console.l('server.js');


jQuery (function() {
    if(server_whitestudio_news_state!=0){ fun_show_update_server_message_emsFormBuilder(server_whitestudio_news_message)}
});


function fun_show_update_server_message_emsFormBuilder(message){
  const wpcontent = document.getElementById('wpbody-content');
  const newItem = document.createElement("DIV");
  newItem.id='id_usm_emsFormBuilder'
  wpcontent.insertBefore(newItem, wpcontent.firstChild);
  document.getElementById('id_usm_emsFormBuilder').innerHTML=message;
 // wpcontent.insertAdjacentElement('afterbegin', message)
}