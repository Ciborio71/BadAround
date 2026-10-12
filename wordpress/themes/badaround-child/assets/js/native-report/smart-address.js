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
    candidates.province = candidates.province.flatMap(v => [v, v.replace(/^(?:città metropolitana di|provincia di)\s+/iu,''), normalize(v)==='citta metropolitana di roma capitale'?'Roma':'']);
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
  let loaderAttempt = 0;
  function loadPlaces(key, doc = root.document) {
    // Prefer an existing Google JavaScript bootstrap, for compatibility with
    // Maps and Legacy. No second script may be injected during an active attempt.
    if (root.google?.maps?.importLibrary) return root.google.maps.importLibrary('places');
    if (loaderPromise) return loaderPromise;
    const attempt = ++loaderAttempt;
    const callback = '__badaroundSmartPlacesReady' + attempt;
    loaderPromise = new Promise((resolve,reject) => {
      const script = doc.createElement('script');
      let settled = false;
      const timer = root.setTimeout(() => finish(new Error('places_timeout')), 15000);
      function finish(error) {
        if (settled) return;
        settled = true;
        root.clearTimeout(timer);
        delete root[callback];
        script.onerror = null;
        // Remove failed/expired scripts before allowing another attempt.
        // Removal prevents a pending external script from executing in normal
        // browsers; a unique callback also fences an exceptionally late load.
        if (error) {
          script.remove();
          reject(error);
          return;
        }
        resolve(root.google.maps.importLibrary('places'));
      }
      root[callback] = () => {
        if (!root.google?.maps?.importLibrary) {
          finish(new Error('places_unavailable'));
          return;
        }
        finish();
      };
      script.onerror = () => finish(new Error('places_unavailable'));
      const url = new URL('https://maps.googleapis.com/maps/api/js');
      url.searchParams.set('key', key);
      url.searchParams.set('v','weekly');
      url.searchParams.set('loading','async');
      url.searchParams.set('callback',callback);
      script.src=url.toString();
      script.async=true;
      script.dataset.baSmartAddress='1';
      try { doc.head.appendChild(script); }
      catch (_) { finish(new Error('places_unavailable')); }
    }).catch(error => {
      // Keep a single rejected attempt from poisoning future explicit retries.
      loaderPromise = null;
      throw error;
    });
    return loaderPromise;
  }
  // The native report does not require a Google Place ID; we intentionally
  // avoid requesting, logging or persisting provider IDs in this adapter.
  function mount(container, config, model) {
    const ui = container.querySelector('[data-native-smart-address]');
    if (!ui) return {dispose() {}};
    if (!config.smartAddress?.key) {ui.hidden=true;return {dispose() {}};}
    const start = ui.querySelector('[data-smart-start]');
    const status = ui.querySelector('[data-smart-status]');
    const holder = ui.querySelector('[data-smart-widget]');
    const paths = LEVELS.map(v => 'location.' + v);
    const privatePaths = ['location.exact_address','location.exact_lat','location.exact_lng'];
    let disposed=false, selected=false, widget, requestId=0, applying=false;
    const say = msg => {if(!disposed)status.textContent=msg;};
    const field = path => container.querySelector('[name="' + path + '"]');
    function assign(path,value) {
      const el = field(path);
      if(!el || el.disabled) return;
      const previous = applying; applying=true;
      try {
        el.value = value === undefined || value === null ? '' : String(value);
        el.dispatchEvent(new root.Event('input',{bubbles:true}));
      } finally {applying=previous;}
    }
    function clearLocation(clearAddress=true) {
      for (const path of paths) assign(path,'');
      if (clearAddress) assign('location.exact_address','');
      assign('location.exact_lat','');
      assign('location.exact_lng','');
    }
    function invalidate(reason,territoryChanged=false) {
      requestId++;
      selected=false;
      // If a manually edited territory could disagree with the selected
      // street address, require the user to confirm/re-enter exact address.
      if (territoryChanged) assign('location.exact_address','');
      assign('location.exact_lat','');
      assign('location.exact_lng','');
      say(reason);
    }
    function changed(event) {
      if (disposed || applying || !event.target?.matches?.('[data-native-input]')) return;
      const path=event.target.name;
      if (!paths.includes(path) && !privatePaths.includes(path)) return;
      // User interaction wins over any asynchronous Place Details response.
      // This works during a pending request AND after a selected suggestion.
      if (requestId || selected) invalidate('Dati geografici modificati: verifica il territorio e inserisci l’indirizzo preciso, se necessario.', paths.includes(path));
    }
    container.addEventListener('input',changed);
    async function choose(event) {
      if (disposed) return;
      const id=++requestId;
      selected=false;
      // Do not submit an old territory or exact coordinate while resolving a new Place.
      clearLocation(true);
      try {
        const place=event.placePrediction?.toPlace?.();
        if (!place) throw new Error('place_unavailable');
        say('Verifica dell’indirizzo in corso…');
        await place.fetchFields({fields:['addressComponents','formattedAddress','location']});
        if (disposed || id!==requestId) return;
        const mapped=resolveTerritory(place.addressComponents,config.smartAddress.territories);
        if (mapped.error === 'outside_italy') {
          say('Il risultato selezionato non è in Italia. Scegli un indirizzo italiano o compila il territorio manualmente.');
          return;
        }
        // Even when Google cannot reconcile the territory, never recover the
        // old values. The user must explicitly enter the canonical hierarchy.
        if (mapped.location) for(const level of LEVELS) assign('location.'+level,mapped.location[level]||'');
        if (place.formattedAddress) assign('location.exact_address',place.formattedAddress);
        const lat=typeof place.location?.lat==='function'?place.location.lat():null;
        const lng=typeof place.location?.lng==='function'?place.location.lng():null;
        // A coordinate pair may accompany only a fully reconciled hierarchy.
        if (mapped.location && Number.isFinite(lat) && Number.isFinite(lng)) {
          assign('location.exact_lat',lat);
          assign('location.exact_lng',lng);
        }
        selected=Boolean(mapped.location);
        say(mapped.location
          ? 'Territorio riconciliato con BadAround. Conferma Regione, Provincia, Comune e Località prima di proseguire.'
          : 'Il territorio canonico non è stato riconosciuto. Compila manualmente Regione, Provincia e Comune; le coordinate Google non sono utilizzate.');
      } catch (_) {
        if (!disposed && id===requestId) say('Risultato Google non disponibile. Compila il territorio e l’indirizzo manualmente.');
      }
    }
    async function activate() {
      if (disposed) return;
      start.disabled=true; say('Caricamento della ricerca Google…');
      try {
        const places=await loadPlaces(config.smartAddress.key);
        if (disposed) return;
        const Element=places.PlaceAutocompleteElement || root.google?.maps?.places?.PlaceAutocompleteElement;
        if (!Element) throw new Error('widget_unavailable');
        widget=new Element({includedRegionCodes:['it']});
        widget.includedRegionCodes=['it'];
        widget.addEventListener('gmp-select',choose);
        holder.replaceChildren(widget);
        start.hidden=true;
        say('Cerca un indirizzo italiano e seleziona un suggerimento. Puoi sempre usare i campi manuali.');
      } catch (_) {
        if (!disposed) {start.disabled=false;say('Google non è disponibile. I campi manuali restano utilizzabili.');}
      }
    }
    start.addEventListener('click',activate);
    return {dispose(){disposed=true;requestId++;start.removeEventListener('click',activate);container.removeEventListener('input',changed);widget?.removeEventListener('gmp-select',choose);}};
  }
  ns.SmartAddress = {resolveTerritory,loadPlaces,mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = {resolveTerritory};
})(typeof window !== 'undefined' ? window : globalThis);
