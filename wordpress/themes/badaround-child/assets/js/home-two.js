(() => {
 'use strict';
 const form=document.querySelector('[data-h2-search]');if(!form)return;
 const input=form.querySelector('[data-h2-input]'),menu=form.querySelector('[data-h2-suggestions]'),nearby=form.querySelector('[data-h2-nearby]'),status=document.getElementById('h2-search-status');
 const config=window.BadAroundHomeTwo||{},fields=['place_id','lat','lng','north','south','east','west'];
 let loader=null,places=null,session=null,timer=null,revision=0,active=-1;
 const tell=text=>{status.textContent=text;};
 function clearResolved(){fields.forEach(name=>{form.elements.namedItem(name).value='';});}
 function close(){revision++;menu.hidden=true;menu.replaceChildren();active=-1;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');}
 input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-expanded','false');input.setAttribute('aria-controls','h2-place-list');menu.id='h2-place-list';menu.setAttribute('role','listbox');
 function loadPlaces(){
  if(loader)return loader;
  if(window.google?.maps?.importLibrary)loader=google.maps.importLibrary('places');
  else if(config.mapsUrl)loader=new Promise((resolve,reject)=>{
   const script=document.createElement('script'),url=new URL(config.mapsUrl),callback='__baHomeTwoPlacesReady';
   const timeout=setTimeout(()=>finish(new Error('Timeout')),12000);
   const finish=error=>{clearTimeout(timeout);delete window[callback];if(error){script.remove();reject(error);}else resolve();};
   window[callback]=()=>finish();script.onerror=()=>finish(new Error('Unavailable'));url.searchParams.set('callback',callback);script.src=url.toString();script.async=true;document.head.append(script);
  }).then(()=>google.maps.importLibrary('places'));
  else return Promise.reject(new Error('Unavailable'));
  loader=loader.then(value=>{places=value;session=new places.AutocompleteSessionToken();return places;}).catch(error=>{loader=null;throw error;});
  return loader;
 }
 async function select(suggestion,mine){
  try{
   const place=suggestion.placePrediction.toPlace();await place.fetchFields({fields:['displayName','formattedAddress','location','viewport']});
   if(mine!==revision)return;
   clearResolved();input.value=place.formattedAddress||place.displayName||'';
   form.elements.namedItem('place_id').value=place.id||suggestion.placePrediction.placeId||'';
   if(place.location){form.elements.namedItem('lat').value=place.location.lat();form.elements.namedItem('lng').value=place.location.lng();}
   if(place.viewport){const ne=place.viewport.getNorthEast(),sw=place.viewport.getSouthWest();for(const [key,value]of Object.entries({north:ne.lat(),east:ne.lng(),south:sw.lat(),west:sw.lng()}))form.elements.namedItem(key).value=value;}
   close();session=new places.AutocompleteSessionToken();tell('Luogo selezionato. Premi Cerca per esplorare la zona.');input.focus();
  }catch(_){tell('Non è stato possibile selezionare il luogo. Puoi riprovare o cercare il testo inserito.');}
 }
 async function predict(){
  const mine=++revision,query=input.value.trim();if(query.length<2){close();return;}
  try{
   await loadPlaces();if(mine!==revision)return;
   const result=await places.AutocompleteSuggestion.fetchAutocompleteSuggestions({input:query,sessionToken:session,includedRegionCodes:['it']});if(mine!==revision)return;
   menu.replaceChildren();active=-1;
   for(const [index,suggestion]of (result.suggestions||[]).filter(s=>s.placePrediction).slice(0,6).entries()){
    const prediction=suggestion.placePrediction,button=document.createElement('button'),strong=document.createElement('strong'),small=document.createElement('small');button.type='button';button.id='h2-place-'+index;button.setAttribute('role','option');button.setAttribute('aria-selected','false');strong.textContent=prediction.mainText?.text||prediction.text?.text||'';small.textContent=prediction.secondaryText?.text||'';button.append(strong,small);button.addEventListener('mousedown',e=>e.preventDefault());button.addEventListener('click',()=>{void select(suggestion,mine);});menu.append(button);
   }
   menu.hidden=!menu.childElementCount;input.setAttribute('aria-expanded',String(!menu.hidden));
  }catch(_){if(mine===revision){close();tell('Suggerimenti non disponibili. Puoi comunque premere Cerca con un indirizzo, comune o quartiere.');}}
 }
 input.addEventListener('focus',()=>{void loadPlaces().catch(()=>{});});
 input.addEventListener('input',()=>{clearResolved();revision++;clearTimeout(timer);menu.hidden=true;input.setAttribute('aria-expanded','false');timer=setTimeout(predict,200);});
 input.addEventListener('keydown',event=>{
  if(event.key==='Escape'){close();return;}
  const options=[...menu.querySelectorAll('button')];if(menu.hidden||!options.length)return;
  if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();active=(active+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;options.forEach((option,i)=>option.setAttribute('aria-selected',String(i===active)));input.setAttribute('aria-activedescendant',options[active].id);options[active].scrollIntoView({block:'nearest'});}
  if(event.key==='Enter'&&active>=0){event.preventDefault();options[active].click();}
 });
 document.addEventListener('click',event=>{if(!form.contains(event.target))close();});
 form.addEventListener('submit',event=>{if(!input.value.trim()){event.preventDefault();input.focus();return;}close();fields.forEach(name=>{const field=form.elements.namedItem(name);if(!field.value)field.disabled=true;});});
 window.addEventListener('pageshow',()=>fields.forEach(name=>{form.elements.namedItem(name).disabled=false;}));
 nearby.addEventListener('click',()=>{
  if(!navigator.geolocation){tell('Gеolocalizzazione non disponibile. Usa la ricerca manuale.');return;}
  nearby.disabled=true;tell('Richiesta della posizione in corso…');
  navigator.geolocation.getCurrentPosition(position=>{const lat=Number(position.coords.latitude),lng=Number(position.coords.longitude);nearby.disabled=false;if(!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180){tell('Posizione non disponibile. Usa la ricerca manuale.');return;}const url=new URL(config.mapUrl||form.action,location.origin);url.searchParams.set('lat',lat);url.searchParams.set('lng',lng);url.searchParams.set('area','Vicino a me');location.assign(url.toString());},error=>{nearby.disabled=false;tell(error.code===1?'Permesso posizione non concesso. Puoi cercare manualmente una zona.':'Posizione non disponibile. Riprova o cerca manualmente.');},{enableHighAccuracy:false,timeout:10000,maximumAge:60000});
 });
})();
