(function(root){
 'use strict';
 const ns=root.BadAroundNative=root.BadAroundNative||{};
 // Optional presentation adapter. Only SmartAddress may write canonical fields.
 function mount(container,config,model,bridge){
  const ui=container.querySelector('[data-native-smart-address]');
  const panel=ui?.querySelector('[data-smart-map-panel]'),canvas=ui?.querySelector('[data-smart-map]');
  const geo=ui?.querySelector('[data-smart-geolocate]'),status=ui?.querySelector('[data-smart-map-status]');
  if(!panel||!canvas||!geo)return {hide(){},show(){},sync(){},dispose(){}};
  const confirmation=ui.querySelector('[data-smart-confirmation]');
  // Keep the verified address next to the result, without changing its data source.
  if(confirmation){panel.after(confirmation);confirmation.tabIndex=-1;}
  let disposed=false,map,marker,dragListener,busy=false;
  let mapTask=Promise.resolve();
  const locked=()=>Boolean(disposed||model.busy||model.snapshot||model.completed);
  const say=text=>{if(!disposed&&status)status.textContent=text;};
  function sync(){geo.disabled=locked()||busy||!config.smartAddress?.key;if(marker)marker.gmpDraggable=!locked()&&!busy;}
  function glyph(){
   const category=model.values['event.category'];
   const colors={vehicle:'#1677E8',property:'#C9153D',hazard:'#F57C00',public_space:'#0AA184',animal:'#7040C8',item_document:'#E3A415'};
   const pin=container.ownerDocument.createElement('span');pin.className='ba-native-location-pin';pin.style.backgroundColor=colors[category]||'#087f8c';
   const radio=Array.from(container.querySelectorAll('.ba-native-category-grid input')).find(el=>el.value===category);
   const icon=radio?.closest('label')?.querySelector('svg');
   if(icon)pin.append(icon.cloneNode(true));
   pin.setAttribute('aria-hidden','true');return pin;
  }
  function show(position){return mapTask=renderMap(position);}
  async function renderMap(position){
   if(disposed)return;
   const ticket=bridge.ticket();
   try{
    await bridge.load();
    const [{Map:GoogleMap},{AdvancedMarkerElement}]=await Promise.all([root.google.maps.importLibrary('maps'),root.google.maps.importLibrary('marker')]);
    if(disposed||!bridge.current(ticket))return;
    panel.hidden=false;
    if(!map){
     map=new GoogleMap(canvas,{center:position,zoom:17,mapId:config.smartAddress?.mapId||'DEMO_MAP_ID',mapTypeControl:false,streetViewControl:false,fullscreenControl:false,gestureHandling:'cooperative',clickableIcons:false});
     marker=new AdvancedMarkerElement({map,position,title:'Posizione riservata dell’evento. Sposta per correggere.',gmpDraggable:true});
     marker.replaceChildren(glyph());
     dragListener=()=>{const p=marker.position;reverse({lat:typeof p.lat==='function'?p.lat():p.lat,lng:typeof p.lng==='function'?p.lng():p.lng});};
     marker.addEventListener('gmp-dragend',dragListener);
    }else{marker.map=map;marker.position=position;marker.replaceChildren(glyph());map.setCenter(position);}
    sync();say('Il segnaposto indica la posizione riservata dell’evento. Puoi correggerla spostandolo.');
   }catch(_){if(!disposed&&bridge.current(ticket))say('Mappa non disponibile. Puoi continuare con l’indirizzo Google confermato.');}
  }
  function revealAddress(ticket){
   const reveal=()=>{
    if(locked()||!bridge.current(ticket)||!confirmation||confirmation.hidden||ui.closest('[data-native-step]')?.hidden)return;
    try{confirmation.focus({preventScroll:true});}catch(_){confirmation.focus();}
    const header=container.ownerDocument.querySelector('.ba-site-header');
    const safeTop=Math.max(16,(header?.getBoundingClientRect().bottom||0)+16);
    const top=Math.max(0,(root.scrollY||0)+confirmation.getBoundingClientRect().top-safeTop);
    const reduced=root.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    root.scrollTo?.({top,behavior:reduced?'instant':'smooth'});
   };
   if(root.requestAnimationFrame)root.requestAnimationFrame(reveal);else reveal();
  }
  async function resolve(position,ticket,reveal=false){
   try{
    if(!Number.isFinite(position.lat)||!Number.isFinite(position.lng)||Math.abs(position.lat)>90||Math.abs(position.lng)>180)throw new Error('invalid_position');
    await bridge.load();
    if(!bridge.current(ticket)||disposed)return;
    const [{Geocoder},{Place}]=await Promise.all([root.google.maps.importLibrary('geocoding'),root.google.maps.importLibrary('places')]);
    if(!bridge.current(ticket)||disposed)return;
    const response=await new Geocoder().geocode({location:position,language:'it'});
    if(!bridge.current(ticket)||disposed)return;
    const result=response.results?.[0];
    if(!result?.place_id)throw new Error('no_address');
    // Resolve an actual Google Place, then use the unchanged canonical resolver.
    const acceptance=bridge.accept(new Place({id:result.place_id}),position,ticket);
    // Accepting starts a fresh Place Details request; retain that request's ticket.
    const acceptedTicket=bridge.ticket();
    const ok=await acceptance;
    if(ok){
     if(reveal)await mapTask;
     if(disposed||!bridge.current(acceptedTicket))return;
     say('Punto e indirizzo verificati. Controlla che corrispondano al luogo dell’evento.');
     if(reveal)revealAddress(acceptedTicket);
    }
    else if(bridge.current(ticket))say('Punto non riconosciuto. Cerca e seleziona un indirizzo Google.');
   }catch(_){if(!disposed&&bridge.current(ticket))say('Non è stato possibile verificare il punto. Cerca e seleziona un indirizzo Google.');}
   finally{if(!disposed){busy=false;geo.textContent='Usa la mia posizione';sync();}}
  }
  function reverse(position){
   if(locked()||busy)return;
   const ticket=bridge.begin();busy=true;sync();say('Verifica del punto e dell’indirizzo in corso…');
   return resolve(position,ticket);
  }
  function geolocate(){
   if(locked()||busy)return;
   if(root.isSecureContext===false||!root.navigator?.geolocation){say('La posizione del dispositivo non è disponibile in questo browser. Puoi cercare un indirizzo.');return;}
   const ticket=bridge.reserve();busy=true;sync();geo.textContent='Ricerca della posizione…';say('Attendi la posizione del dispositivo e controlla che sia il luogo dell’evento.');
   const failed=error=>{
    if(disposed)return;busy=false;geo.textContent='Usa la mia posizione';sync();
    if(!bridge.current(ticket))return;
    const messages={1:'Permesso alla posizione negato. Consenti l’accesso alla posizione nelle impostazioni del browser oppure cerca un indirizzo.',2:'Non è stato possibile rilevare la posizione del dispositivo. Riprova oppure cerca un indirizzo.',3:'La ricerca della posizione ha richiesto troppo tempo. Riprova oppure cerca un indirizzo.'};
    say(messages[error?.code]||'La posizione del dispositivo non è disponibile. Puoi cercare un indirizzo.');
   };
   try{root.navigator.geolocation.getCurrentPosition(p=>{
    if(disposed||!bridge.current(ticket)){busy=false;geo.textContent='Usa la mia posizione';sync();return;}
    resolve({lat:p.coords.latitude,lng:p.coords.longitude},ticket,true);
   },failed,{enableHighAccuracy:true,timeout:12000,maximumAge:0});}catch(_){failed();}
  }
  function categoryChanged(event){if(event.target?.name==='event.category'&&marker)marker.replaceChildren(glyph());}
  geo.addEventListener('click',geolocate);container.addEventListener('input',categoryChanged);sync();
  return {show,sync,hide(){panel.hidden=true;if(marker)marker.map=null;},dispose(){disposed=true;if(marker&&dragListener)marker.removeEventListener('gmp-dragend',dragListener);if(marker)marker.map=null;geo.removeEventListener('click',geolocate);container.removeEventListener('input',categoryChanged);}};
 }
 ns.LocationMap={mount};
})(typeof window!=='undefined'?window:globalThis);
