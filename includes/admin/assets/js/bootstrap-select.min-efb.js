
/* 
 Created by : Hassan Tafreshi
 Copy right owner : whiteStudio.team
 Desc: This multi-select is designed base on boostrap 5 and used for Easy form builder Wordpress Plugin
 */

 let idOfMenu_efb ="";
 let default_val_efb ="Select an option";
 let inputSearch_efb;
 let ArrSearch_efb = Array.prototype;
//data-id va data=setid bari baghi bejoz Elicon iki bashad va id bashad

 function returnValueSelectedOfListEfb(dataSetId){
     
     const l = document.querySelector(`[data-idset="${dataSetId}"].efb.efblist.inplist`)
     
     let s = l.dataset.select.split('@efb!');
     s= s.slice(0,(s.length-1))
     let r =''
     s.forEach(element => {
         const d = document.querySelector(`.efb.table.${dataSetId}`)
     
         let v= d.querySelector(`[data-row="${element}"]`)
         r += v.dataset.name +', '
     });
     
  
     r=r.slice(0,(r.length-2));
     
     return r;
 }
 function returnValueSelectedOfIconEfb(dataSetId){
     //select icon
     const l = document.querySelector(`[data-idset="${dataSetId}"].efb.efblist.inplist`)
     
     let r = l.dataset.select
     const icon = `bi-${bootstrap_icons[r]}`
     
     return icon;
 }


 function onInputEventEFB(e) {
     inputSearch_efb = e;    
     var table1 = document.querySelector(`.${e.dataset.id}.table` );
        ArrSearch_efb.forEach.call(table1.tBodies, function(tbody) {
             ArrSearch_efb.forEach.call(tbody.rows, filterTable_efb);
         });
 }
 
 function filterTable_efb(row) {
     var text = row.textContent.toLowerCase();
    var val = inputSearch_efb.value.toLowerCase();
   row.style.display = text.indexOf(val) === -1 ? 'none' : 'table-row';
 }
 
 
 function FunSearchTableEfb(dataSetId){
     let inputs =document.querySelector(`[data-id="${dataSetId}"].searchBox` );
      onInputEventEFB(inputs)
 }
 
  document.addEventListener("click", (evnt) => {

    //console.log(evnt.target);
      const d=evnt.target.dataset.id;      
      IsmenuC=(el)=>{
       let id =evnt.target.dataset.id
        
     
       
        while (el.parentNode!=null &&  id!='wpbody-content' && id!='sideMenuFEfb' && id!="deleteOption" ) {
         if(el!=null) el=el.parentNode;
          
         
          id= el!=null && el["id"]!=undefined ? el.id :'no' ;
        }
        // }else{id="wpbody-content"}
        if(el==null || el["id"]==undefined ) id="no";
        return id;
      }      
      if(document.getElementById('sideBoxEfb') && document.getElementById('sideBoxEfb').classList.contains('show') 
      && evnt.target.id!="efbSetting" &&  evnt.target.id!="BtnCSideEfb" && !evnt.target.classList.contains('BtnSideEfb') && !evnt.target.classList.contains('wp-toolbar')){                 
      
        let id=IsmenuC(evnt.target);
        //console.log(id , d)
        if(id=="wpbody-content") sideMenuEfb(0)
      }
      
      if(evnt.target.classList.contains('efblist')){
        if(evnt.target.classList.contains('disabled')==true){return 0;}
          if(idOfMenu_efb!=d){
              if(idOfMenu_efb.length>1){
                 c_m_efb();
              }else{
                 idOfMenu_efb =d;
                 a_m_efb();
              }
          }else{
              if(evnt.target.dataset.parent){
                 c_m_efb();
              }
          }
         
      }else if (evnt.target.tagName=="TD" ||evnt.target.tagName=="TH"  ){
          
          //console.log(idOfMenu_efb)
       const e =evnt.target.parentNode 
         if(e.classList.contains('efblist')){
            default_val_efb =efb_var.text.selectOption;
             let l =  document.querySelector(`[data-id="${idOfMenu_efb}"]`);
             
             if(l.dataset.no==1){
                 let fc = e.querySelector("TH")
                 if(e.dataset.row ==l.dataset.select){
                     l.dataset.select="" ;
                     l.innerHTML=default_val_efb;
                     fc.classList.remove("border-info"); 
                    if (l.dataset.icon==1) fc.className="bi-square efb";
                 }else{
                    
                     if(l.dataset.select.length>0) {
                         let x= document.querySelector(`[data-id="${idOfMenu_efb}"] [data-row="${l.dataset.select}"]`);
                         x.classList.remove("border-info");                
                         if (l.dataset.icon==1) x.querySelector("TH").className="bi-square efb";  
                     }
                     l.dataset.select=e.dataset.row;
                     l.innerHTML=e.dataset.name;
                     //console.log(l.dataset)
                     if (l.dataset.icon==1) fc.className="bi-check-square text-info efb";
                     e.className += " border-info";
                 }
                 c_m_efb();
                 
             }else{

                 const r= e.dataset.row;
                 let fc = e.querySelector("TH")
                 //console.log(r, l.dataSetId)
                 //778899
                 if(l.dataset.select.includes(r)){
                    
                     l.dataset.select= l.dataset.select.replace(`${r} @efb!`, '');
                     const v= l.innerHTML.length == (l.innerHTML.indexOf(',')+1) ? default_val_efb :''
                     l.innerHTML =  l.innerHTML.replace(`${e.dataset.name},`,v);
                     if (l.dataset.icon==1) fc.className="bi-square efb";
                     e.className="efblist";
                   /*   l.classList.remove("border-info");
                    l.classList.add("border-success"); */
                    if(l.dataset.select.length<1){
                        l.innerHTML=default_val_efb;
                        l.classList.remove("border-success");
                    }
                 }else{
                    const s= l.dataset.select.split("@efb!");
                     if((s.length)<=l.dataset.no){
                         if (l.dataset.icon==1) fc.className="bi-check-square text-info efb";
                         l.dataset.select.length<1 ?  l.innerHTML=e.dataset.name+"," :l.innerHTML+=e.dataset.name+","
                         l.dataset.select+=`${e.dataset.row} @efb!`;
                         l.classList.remove("border-info");
                        l.classList.add("border-success");
 
                     }

                 }
           
             }  
             let price =0; 
             switch(l.id){
                 case "iconEl":
                        set_icon_valEfb(l)
                    break;
                 }                
                 if((typeof ajax_object_efm) == 'object'){
                    
                    const v= l.innerHTML.replaceAll(`,` , "@efb!");
                    const el_o = l.dataset.select.split(" @efb!")
                    
                     let ob = valj_efb.find(x => x.id_ === l.dataset.vid); 
                     
                     let o = [{ id_: l.dataset.vid, name: ob.name,amount:ob.amount, type:ob.type, value: v, session: sessionPub_emsFormBuilder }];                     
                     if(valj_efb[0].type=="payment" && l.classList.contains('payefb')){ 
                         
                         let ids="";
                         for (let el of el_o){
                             const i= valueJson_ws.findIndex(x=>x.id_ == `${el}`);
                             //console.log(el,valueJson_ws[i])
                           if(i!=-1){
                            price += parseFloat(valueJson_ws[i].price);
                            ids +=`${valueJson_ws[i].id_},`;
                           }
                         }
                         
                         if(price>0){
                            ids= ids.slice(0,-1)
                             let q={price:price, ids:ids}
                             
                             Object.assign(o[0],q)
                         
                         }
                        }
                     indx = get_row_sendback_by_id_efb(l.dataset.vid);
                     if (indx == -1) {
                          sendBack_emsFormBuilder_pub.push(o[0]) 
                          
                     }else{ 
                        //console.log(`[${v.trim()}] , [${ajax_object_efm.text.selectOption.trim()}]` , v.trim()!=efb_var.text.selectOption.trim())
                        if(v.trim()!=efb_var.text.selectOption.trim()){
                            
                            sendBack_emsFormBuilder_pub[indx].value=v;
                            if(valj_efb[0].type=="payment" && l.classList.contains('payefb')) {
                                sendBack_emsFormBuilder_pub[indx].price=price                             
                                sendBack_emsFormBuilder_pub[indx].ids=o[0].ids
                               };
                        }else{
                            sendBack_emsFormBuilder_pub.splice(indx,1)
                            //console.log(sendBack_emsFormBuilder_pub , 'Select an Option')
                        }
                     }

                     if(valj_efb[0].type=="payment" && l.classList.contains('payefb'))fun_total_pay_efb()
                     localStorage.setItem('sendback',JSON.stringify(sendBack_emsFormBuilder_pub))
                     
                     const pl = l.id.split('_');
                     const idm = pl[0] +'_-message'
                     
                     if(el_o.length>1){
                         document.getElementById(idm).innerHTML="";
                         if (l.classList.contains('border-danger')) l.classList.remove('border-danger')
                    }else{
                        if (l.classList.contains('border-danger')){
                            //console.error('add border');
                        }
                       
                     }
                 }     
         }else{
             if(idOfMenu_efb.length>1){
                 c_m_efb();
             }
         }
      }else if (idOfMenu_efb!=""){          
         c_m_efb();
      }
      
  });
 
  const c_m_efb=()=>{
     //hide the list select
        let m = document.querySelector(`[data-list="${idOfMenu_efb}"]`);        
        if(m) m.classList.add("d-none");
        idOfMenu_efb="";
       }
   const a_m_efb=()=>{
       //Show the list select
       let m = document.querySelector(`[data-list="${idOfMenu_efb}"]`);
       const n =idOfMenu_efb.indexOf('menu-')!=-1 ? idOfMenu_efb.slice(5):idOfMenu_efb;
       //console.log(idOfMenu_efb ,n ,idOfMenu_efb.indexOf('menu-') );
       let el  = n!=idOfMenu_efb ? document.getElementById(n+"_options") :m;
       let width = el.offsetWidth;
       if(width==0){
        el = el.parentNode;
        width = el.offsetWidth;
       }
       m.style.width =width+"px";
        if(m) m.classList.remove("d-none");
       }

    const set_icon_valEfb=(el)=>{
        
        let di = '';
        const idset=el.dataset.idset.includes("step-")?el.dataset.idset.slice(5) : el.dataset.idset;
        //console.log(el.dataset.idset.includes("step-"), el.dataset,idset)
        const indx = idset!="button_group_" ? valj_efb.findIndex(x=>x.id_ ==idset) : 0
        let icon=""
        //console.log(idset,indx)
        
        if (el.dataset.side == "undefined" || el.dataset.side == "") {
        di = indx!=0 ? `${valj_efb[indx].id_}_icon`:'button_group_icon'
        const k  = isNumericEfb(idset)  ? 'step-'+idset : idset
        icon= valj_efb[indx].icon = returnValueSelectedOfIconEfb(k);
        }else if(el.dataset.side == "DoneIconEfb"){
            //di =el.dataset.side ;
            
            icon= valj_efb[0].thank_you_message.icon = returnValueSelectedOfIconEfb(idset);

        } else {
        const i = returnValueSelectedOfIconEfb(idset);
        icon=i
        //console.log(`i====================>${i} ${el.dataset.side}` ,`=========================>${el.dataset.side.includes("Next")}`);
        di = el.dataset.side.includes("Next") ? `button_group_Next_icon` : `button_group_Previous_icon`
        el.dataset.side.includes("Next") ? valj_efb[0].button_Next_icon = i : valj_efb[0].button_Previous_icon = i
        }
        
        //Bug replace find bi-XXXXX in class
        if(di!=''){
            const r =iconChangerEfb(document.getElementById(`${di}`).className,icon)
            document.getElementById(di).className =r           
            if(r.includes('bi-')==false && icon!="bi-undefined"){
                document.getElementById(di).classList.add(icon);
                document.getElementById(di).classList.remove('d-none');
            }else if(r.includes('bi-')==true && icon=="bi-undefined"){
                document.getElementById(di).classList.add('d-none');
            }else if(r.includes('bi-')==true && icon!="bi-undefined"){
                document.getElementById(di).classList.remove('d-none');
            }
        }
    }


 