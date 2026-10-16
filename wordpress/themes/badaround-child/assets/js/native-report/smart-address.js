(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  const LEVELS = ['region', 'province', 'municipality', 'locality'];
  const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLocaleLowerCase('it-IT');
  const component = (components, type) => (components || []).find(item => (item.types || []).includes(type));
  const variants = part => part ? [part.longText, part.shortText].filter(Boolean) : [];

  // 2026 territorial-unit sigle, matched by ISTAT UTS code against the
  // existing staging canonical province terms (110/110, read-only).
  // Source: guglielmo/geojson-italy, commit
  // ba3347df3a53352891109f8198c597a0273684c0,
  // geojson/limits_IT_provinces.geojson: prov_uts_code -> prov_acr.
  // This does NOT create identities: the canonical registry and parent must
  // match. Unmapped codes fail closed on sigla-only Google responses.
  const SIGLA_BY_UTS_CODE = Object.freeze({"100":"PO","101":"KR","102":"VV","103":"VB","108":"MB","109":"FM","110":"BT","113":"OT","114":"NU","115":"OR","116":"OG","117":"VS","119":"CI","201":"TO","210":"GE","215":"MI","227":"VE","237":"BO","248":"FI","258":"RM","263":"NA","272":"BA","280":"RC","282":"PA","283":"ME","287":"CT","312":"SS","318":"CA","002":"VC","003":"NO","004":"CN","005":"AT","006":"AL","007":"AO","008":"IM","009":"SV","011":"SP","012":"VA","013":"CO","014":"SO","016":"BG","017":"BS","018":"PV","019":"CR","020":"MN","021":"BZ","022":"TN","023":"VR","024":"VI","025":"BL","026":"TV","028":"PD","029":"RO","030":"UD","031":"GO","032":"TS","033":"PC","034":"PR","035":"RE","036":"MO","038":"FE","039":"RA","040":"FC","041":"PU","042":"AN","043":"MC","044":"AP","045":"MS","046":"LU","047":"PT","049":"LI","050":"PI","051":"AR","052":"SI","053":"GR","054":"PG","055":"TR","056":"VT","057":"RI","059":"LT","060":"FR","061":"CE","062":"BN","064":"AV","065":"SA","066":"AQ","067":"TE","068":"PE","069":"CH","070":"CB","071":"FG","073":"TA","074":"BR","075":"LE","076":"PZ","077":"MT","078":"CS","079":"CZ","081":"TP","084":"AG","085":"CL","086":"EN","088":"RG","089":"SR","093":"PN","094":"IS","096":"BI","097":"LC","098":"LO","099":"RN"});

  // Conservative orthographic equivalence; every collision under a canonical
  // parent is ambiguous and rejected rather than selected by first match.
  const normalName = value => normalize(value)
    .replace(/[’‘`´']/g,' ')
    .replace(/[‐‑‒–—-]/g,' ')
    .replace(/\s+/g,' ').trim();
  const administrativeName = value => normalName(value)
    // "Provincia dell'Aquila" -> "l aquila": preserve the article in
    // the canonical name rather than treating it as administrative noise.
    .replace(/^(?:provincia(?: autonoma)?|citta metropolitana|libero consorzio(?: comunale)?)\s+dell\s+/u,'l ')
    .replace(/^(?:provincia(?: autonoma)?|citta metropolitana|libero consorzio(?: comunale)?)(?: di| del| della| dello| dei| delle)?\s+/u,'')
    .trim();
  // Institutional spelling confirmed by Città metropolitana di Roma
  // Capitale (https://www.cittametropolitanaroma.it/homepage/la-citta-metropolitana/).
  // The alias is bound to existing canonical province UTS code 258.
  const VERIFIED_ADMIN_ALIASES = Object.freeze({'258':['Roma Capitale']});
  const namesOf = (value, level) => {
    const raw=String(value||'').trim();
    const parts=raw.split('/').map(x=>x.trim()).filter(Boolean);
    // Slashed canonical bilingual labels are declared variants; never
    // construct new municipality identities from partial substrings.
    const variants=[raw,...(level==='region'||level==='province'?parts:[])];
    return [...new Set(variants.map(x=>level==='province'?administrativeName(x):normalName(x)).filter(Boolean))];
  };
  function resolveTerritory(components, registry) {
    const entries=Array.isArray(registry)?registry:[];
    const country=component(components,'country');
    if(!country || !variants(country).some(v=>['it','italia','italy'].includes(normalName(v))))
      return {error:'outside_italy'};
    const byParent=new Map();
    for(const e of entries) {
      const id=Number(e.id),parent=Number(e.parent);
      if(!Number.isSafeInteger(id)||id<=0||!Number.isSafeInteger(parent)||parent<0)continue;
      if(!byParent.has(parent))byParent.set(parent,[]);
      byParent.get(parent).push(e);
    }
    const candidates={
      region:variants(component(components,'administrative_area_level_1')),
      province:variants(component(components,'administrative_area_level_2')),
      municipality:variants(component(components,'locality'))
        .concat(variants(component(components,'administrative_area_level_3'))),
      locality:variants(component(components,'sublocality'))
        .concat(variants(component(components,'sublocality_level_1')),
                variants(component(components,'neighborhood')))
    };
    const result={},ids={};
    const select=(level,parent,names,aliasCodes=[])=>{
      const terms=byParent.get(parent)||[];
      const keys=new Set(names.flatMap(n=>namesOf(n,level)));
      const alias=new Set(aliasCodes);
      const matches=terms.filter(e=>{
        if(namesOf(e.name,level).some(k=>keys.has(k)))return true;
        if(level!=='province')return false;
        const code=String(e.code||'');
        return alias.has(code) || (VERIFIED_ADMIN_ALIASES[code]||[])
          .some(n=>namesOf(n,'province').some(k=>keys.has(k)));
      });
      return matches.length===1?{term:matches[0]}:{error:'unresolved_'+level};
    };
    const region=select('region',0,candidates.region);
    if(region.error)return {error:region.error};
    result.region=region.term.name;ids.region=Number(region.term.id);
    const pComponent=component(components,'administrative_area_level_2');
    const sigla=normalName(pComponent?.shortText||'').toUpperCase();
    // Abbreviation is meaningful only when accompanied by an explicit
    // administrative_area_level_2 component and a verified UTS code.
    const codes=Object.keys(SIGLA_BY_UTS_CODE)
      .filter(k=>SIGLA_BY_UTS_CODE[k]===sigla);
    const province=select('province',ids.region,candidates.province,codes);
    if(province.error)return {error:province.error,partial:result};
    result.province=province.term.name;ids.province=Number(province.term.id);
    // Both Google levels may contain municipal names. If each independently
    // identifies a DIFFERENT canonical municipality, reject the conflict.
    // A sublocality is never promoted to municipality via free text.
    const municipalities=byParent.get(ids.province)||[];
    const separate=[
      variants(component(components,'locality')),
      variants(component(components,'administrative_area_level_3'))
    ].filter(a=>a.length);
    const matched=new Map();
    for(const group of separate){
      const keys=new Set(group.flatMap(x=>namesOf(x,'municipality')));
      const hit=municipalities.filter(x=>namesOf(x.name,'municipality').some(k=>keys.has(k)));
      if(hit.length>1)return {error:'unresolved_municipality',partial:result};
      if(hit.length===1)matched.set(Number(hit[0].id),hit[0]);
    }
    if(matched.size!==1)return {error:'unresolved_municipality',partial:result};
    const municipal=[...matched.values()][0];
    result.municipality=municipal.name;ids.municipality=Number(municipal.id);
    // Google sometimes puts a fraction in locality and the actual Comune
    // in administrative_area_level_3. Resolve that fraction ONLY if it is a
    // pre-existing child of the confirmed canonical municipality.
    const localityNames=[...candidates.locality];
    const gLocality=variants(component(components,'locality'));
    if(gLocality.length &&
      !gLocality.some(v=>namesOf(municipal.name,'municipality').includes(normalName(v))))
      localityNames.push(...gLocality);
    const locality=select('locality',ids.municipality,localityNames);
    if(locality.term){result.locality=locality.term.name;ids.locality=Number(locality.term.id);}
    else result.locality='';
    return {location:result,termIds:ids,country:'Italy'};
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
    // No provider key: display the manual fallback rather than hiding the whole address workflow.
    const start = ui.querySelector('[data-smart-start]');
    const status = ui.querySelector('[data-smart-status]');
    const holder = ui.querySelector('[data-smart-widget]');
    const confirmation=ui.querySelector('[data-smart-confirmation]');
    const confirmationLabel=ui.querySelector('[data-smart-address-label]');
    const manualButton=ui.querySelector('[data-smart-manual]');
    const manualFields=container.querySelector('[data-native-location-fields]');
    let manual=false;
    function syncVisibility(){if(manualFields)manualFields.hidden=!manual;}
    function offerManual(message){say(message+' Puoi utilizzare i campi manuali.');if(manualButton)manualButton.hidden=false;if(confirmation)confirmation.hidden=true;}
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
      if(confirmation)confirmation.hidden=true;
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
      if(confirmation)confirmation.hidden=true;
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
          offerManual('Risultato fuori Italia. Inserisci un indirizzo italiano manualmente.');
          return;
        }
        // Even when Google cannot reconcile the territory, never recover the
        // old values. The user must explicitly enter the canonical hierarchy.
        if (mapped.location) for(const level of LEVELS) assign('location.'+level,mapped.location[level]||'');
        const street = variants(component(place.addressComponents,'route'))[0] || '';
        const number = variants(component(place.addressComponents,'street_number'))[0] || '';
        const formatted = String(place.formattedAddress||'').trim();
        // Google may place the selected square/POI name in the prediction's
        // structured mainText, while Place Details formattedAddress contains
        // only its postal locality. The prediction belongs to the selected
        // Google result, NOT to the user's free-text query.
        const prediction=event.placePrediction;
        const suggestionName=String(
          prediction?.structuredFormat?.mainText?.text ||
          prediction?.text?.text?.split(',')[0] || ''
        ).trim();
        const administrative=new Set([
          ...LEVELS.map(level=>normalize(mapped.location?.[level])),
          ...['Italia','Italy'].map(normalize)
        ]);
        const isDistinctName=suggestionName &&
          !administrative.has(normalize(suggestionName)) &&
          !/^\\d{5}(?:\\s|$)/.test(suggestionName) &&
          suggestionName.length <= 160;
        const verifiedName=street || (isDistinctName?suggestionName:'');
        const verifiedStreet=street+(number?', '+number:'');
        const prefix=street?verifiedStreet:verifiedName;
        const fullAddress=prefix && !normalize(formatted).includes(normalize(verifiedName))
          ? [prefix,formatted].filter(Boolean).join(', ')
          : formatted;
        if (fullAddress) assign('location.exact_address',fullAddress.slice(0,500));
        const lat=typeof place.location?.lat==='function'?place.location.lat():null;
        const lng=typeof place.location?.lng==='function'?place.location.lng():null;
        // A coordinate pair may accompany only a fully reconciled hierarchy.
        if (mapped.location && Number.isFinite(lat) && Number.isFinite(lng)) {
          assign('location.exact_lat',lat);
          assign('location.exact_lng',lng);
        }
        selected=Boolean(mapped.location && fullAddress);
        if(selected){manual=false;syncVisibility();if(confirmationLabel)confirmationLabel.textContent=fullAddress;if(confirmation)confirmation.hidden=false;}
        else offerManual('Indirizzo incompleto. Inserisci la posizione manualmente.');
        if(!mapped.location)offerManual('Territorio non riconosciuto. Inserisci manualmente.');
        else if(selected)say('Posizione riconosciuta.');
      } catch (_) {
        if (!disposed && id===requestId) offerManual('Google non disponibile. Inserisci la posizione manualmente.');
      }
    }
    async function activate() {
      if (disposed || start.disabled || widget) return;
      start.disabled=true; say('Caricamento della ricerca Google…');
      try {
        if (!config.smartAddress?.key) throw new Error('places_unavailable');
        const places=await loadPlaces(config.smartAddress.key);
        if (disposed) return;
        const Element=places.PlaceAutocompleteElement || root.google?.maps?.places?.PlaceAutocompleteElement;
        if (!Element) throw new Error('widget_unavailable');
        widget=new Element({includedRegionCodes:['it']});
        widget.includedRegionCodes=['it'];
        widget.addEventListener('gmp-select',choose);
        holder.replaceChildren(widget);
        start.hidden=true;
        say('Cerca un indirizzo italiano e seleziona un suggerimento.');
        try{widget.focus()}catch(_){}
      } catch (_) {
        if (!disposed) {start.disabled=false;offerManual('Google non è disponibile. Inserisci la posizione manualmente.');}
      }
    }
    function openManual(){
      requestId++;selected=false;manual=true;if(confirmation)confirmation.hidden=true;clearLocation(true);syncVisibility();
      say('Compila Regione, Provincia, Comune e indirizzo preciso. La località è facoltativa.');
      field('location.region')?.focus?.();
    }
    function canAdvance(){
      if(selected&&(!confirmation||!confirmation.hidden)&&!manual)return true;
      if(manual){
        const required=['location.region','location.province','location.municipality','location.exact_address'];
        const missing=required.find(path=>!String(field(path)?.value||'').trim());
        if(!missing)return true;
        say('Completa Regione, Provincia, Comune e indirizzo per continuare.');
        field(missing)?.focus?.();return false;
      }
      say('Seleziona un indirizzo dai suggerimenti Google prima di continuare.');
      // Navigation alone must not initialize Google: only the search field may do so.
      return false;
    }
    function reset(){
      requestId++;selected=false;manual=false;
      if(confirmation)confirmation.hidden=true;
      if(confirmationLabel)confirmationLabel.textContent='';
      if(manualButton)manualButton.hidden=Boolean(config.smartAddress?.key);
      syncVisibility();
      say('Seleziona il campo per cercare un luogo.');
    }
    function changedGoogle(event){
      if(!selected)return;
      if(event.type==='gmp-select')return;
      invalidate('Indirizzo modificato: seleziona di nuovo un suggerimento.',true);
    }
    holder.addEventListener('input',changedGoogle);
    holder.addEventListener('change',changedGoogle);
    start.addEventListener('focus',activate);
    start.addEventListener('click',activate);
    manualButton?.addEventListener('click',openManual);
    if(!config.smartAddress?.key)offerManual('Google non configurato. Inserisci la posizione manualmente.');
    syncVisibility();
    return {canAdvance,syncVisibility,reset,dispose(){disposed=true;requestId++;start.removeEventListener('focus',activate);start.removeEventListener('click',activate);manualButton?.removeEventListener('click',openManual);container.removeEventListener('input',changed);holder.removeEventListener('input',changedGoogle);holder.removeEventListener('change',changedGoogle);widget?.removeEventListener('gmp-select',choose);}};
  }
  ns.SmartAddress = {resolveTerritory,loadPlaces,mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = {resolveTerritory};
})(typeof window !== 'undefined' ? window : globalThis);
