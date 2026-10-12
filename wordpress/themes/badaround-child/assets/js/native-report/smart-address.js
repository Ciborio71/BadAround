(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  const LEVELS = ['region', 'province', 'municipality', 'locality'];
  const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLocaleLowerCase('it-IT');
  const component = (components, type) => (components || []).find(item => (item.types || []).includes(type));
  const variants = part => part ? [part.longText, part.shortText].filter(Boolean) : [];
  function resolveTerritory(components, registry) {
    const entries = Array.isArray(registry) ? registry : [];
    const country = component(components, 'country');
    if (!country || !variants(country).some(v => normalize(v) === 'it' || normalize(v) === 'italia')) return {error:'outside_italy'};
    const candidates = {
      region: variants(component(components, 'administrative_area_level_1')),
      province: variants(component(components, 'administrative_area_level_2')),
      municipality: variants(component(components, 'locality')).concat(variants(component(components, 'administrative_area_level_3'))),
      locality: variants(component(components, 'sublocality')).concat(variants(component(components, 'sublocality_level_1')),variants(component(components, 'neighborhood')))
    };
    // Only explicit administrative component names/aliases; never infer identity from free-text address.
    candidates.province = candidates.province.flatMap(v => [v, v.replace(/^(?:città metropolitana di|provincia di)\s+/iu,'')]);
    const result = {}, ids = {};
    let parent = 0;
    for (const level of LEVELS) {
      const names = new Set(candidates[level].map(normalize).filter(Boolean));
      const matches = entries.filter(term => Number(term.parent) === parent && names.has(normalize(term.name)));
      if (level === 'locality' && matches.length === 0) {result[level]='';break;}
      if (matches.length !== 1) return {error:'unresolved_' + level, partial:result};
      result[level] = matches[0].name;
      ids[level] = Number(matches[0].id);
      parent = ids[level];
    }
    return {location:result, termIds:ids};
  }
  let loaderPromise;
  function loadPlaces(key, doc = root.document) {
    if (root.google?.maps?.importLibrary) return root.google.maps.importLibrary('places');
    if (loaderPromise) return loaderPromise;
    loaderPromise = new Promise((resolve,reject) => {
      const callback = '__badaroundSmartPlacesReady';
      const script = doc.createElement('script');
      const timer = root.setTimeout(() => {cleanup();reject(new Error('places_timeout'));}, 15000);
      function cleanup() {root.clearTimeout(timer);delete root[callback];}
      root[callback] = () => {
        cleanup();
        if (!root.google?.maps?.importLibrary) return reject(new Error('places_unavailable'));
        resolve(root.google.maps.importLibrary('places'));
      };
      script.onerror = () => {cleanup();reject(new Error('places_unavailable'));};
      const url = new URL('https://maps.googleapis.com/maps/api/js');
      url.searchParams.set('key', key);
      url.searchParams.set('v','weekly');
      url.searchParams.set('loading','async');
      url.searchParams.set('callback',callback);
      script.src=url.toString();script.async=true;script.dataset.baSmartAddress='1';
      doc.head.appendChild(script);
    }).catch(error => {loaderPromise=null;throw error;});
    return loaderPromise;
  }
  function mount(container, config, model) {
    const ui = container.querySelector('[data-native-smart-address]');
    if (!ui || !config.smartAddress) return {dispose() {}};
    const start = ui.querySelector('[data-smart-start]');
    const status = ui.querySelector('[data-smart-status]');
    const holder = ui.querySelector('[data-smart-widget]');
    const locationPaths = LEVELS.map(v => 'location.' + v);
    const exactPaths = ['location.exact_address','location.exact_lat','location.exact_lng'];
    let disposed=false, selected=false, widget, requestId=0;
    const say = msg => {if(!disposed)status.textContent=msg;};
    const field = path => container.querySelector('[name="' + path + '"]');
    function assign(path,value) {
      const el = field(path);
      if(!el || el.disabled) return;
      el.value = value === undefined || value === null ? '' : String(value);
      el.dispatchEvent(new root.Event('input',{bubbles:true}));
    }
    function clearSelection() {
      if(!selected)return;
      selected=false;requestId++;
      for (const path of ['location.exact_lat','location.exact_lng']) assign(path,'');
      say('Indirizzo modificato: verifica il territorio e le coordinate prima di proseguire.');
    }
    function changed(event) {
      if (!selected || !event.isTrusted || !event.target?.matches?.('[data-native-input]')) return;
      const path = event.target.name;
      if (locationPaths.concat(exactPaths).includes(path)) clearSelection();
    }
    container.addEventListener('input',changed);
    async function choose(event) {
      const id=++requestId;
      try {
        const place=event.placePrediction?.toPlace?.();
        if(!place)throw new Error('place_unavailable');
        say('Verifica dell’indirizzo in corso…');
        await place.fetchFields({fields:['addressComponents','formattedAddress','location','id']});
        if(disposed || id!==requestId)return;
        const mapped=resolveTerritory(place.addressComponents,config.smartAddress.territories);
        if(mapped.error==='outside_italy'){say('Il risultato selezionato non è in Italia. Usa la ricerca italiana o il territorio manuale.');return;}
        const lat=typeof place.location?.lat==='function'?place.location.lat():null;
        const lng=typeof place.location?.lng==='function'?place.location.lng():null;
        selected=false;
        if(mapped.location) for(const level of LEVELS)assign('location.'+level,mapped.location[level]||'');
        if(place.formattedAddress)assign('location.exact_address',place.formattedAddress);
        if(Number.isFinite(lat)&&Number.isFinite(lng)){assign('location.exact_lat',lat);assign('location.exact_lng',lng);}
        else {assign('location.exact_lat','');assign('location.exact_lng','');}
        selected=true;
        say(mapped.location
          ? 'Territorio riconciliato con BadAround. Verifica Regione, Provincia, Comune e Località prima di proseguire.'
          : 'Google ha trovato l’indirizzo, ma il territorio canonico non è verificato. Compila o correggi manualmente Regione, Provincia e Comune. Nessun termine sarà creato.');
      } catch (_) {if(!disposed && id===requestId)say('Impossibile leggere il risultato Google. Usa i campi manuali.');}
    }
    async function activate() {
      if(disposed)return;
      start.disabled=true;say('Caricamento della ricerca Google…');
      try {
        const places=await loadPlaces(config.smartAddress.key);
        if(disposed)return;
        const Element=places.PlaceAutocompleteElement || root.google?.maps?.places?.PlaceAutocompleteElement;
        if(!Element)throw new Error('widget_unavailable');
        widget=new Element({includedRegionCodes:['it']});
        widget.includedRegionCodes=['it'];
        widget.addEventListener('gmp-select',choose);
        holder.replaceChildren(widget);
        start.hidden=true;
        say('Cerca un indirizzo italiano, quindi seleziona un suggerimento. Il territorio manuale resta disponibile.');
      } catch (_) {
        if(!disposed){start.disabled=false;say('Google non è disponibile. Puoi compilare tutti i campi manualmente oppure riprovare.');}
      }
    }
    start.addEventListener('click',activate);
    return {dispose(){disposed=true;requestId++;start.removeEventListener('click',activate);container.removeEventListener('input',changed);widget?.removeEventListener('gmp-select',choose);}};
  }
  ns.SmartAddress = {resolveTerritory,loadPlaces,mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = {resolveTerritory};
})(typeof window !== 'undefined' ? window : globalThis);
