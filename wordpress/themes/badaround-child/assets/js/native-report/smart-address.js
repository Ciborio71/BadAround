(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};

  const LEVELS = ['region', 'province', 'municipality', 'locality'];
  // Auditable region-scoped province/Uts sigle. Sources: ISTAT 2017 list;
  // ISTAT SITUAS Sardinia update 2026-06-18 (OT,VS,OG,SU).
  // A code is NEVER an identity without a matching canonical ba_territorio child.
  const PROVINCE_SIGLE = {"Abruzzo":"Chieti:CH;L'Aquila:AQ;Pescara:PE;Teramo:TE","Basilicata":"Matera:MT;Potenza:PZ","Calabria":"Catanzaro:CZ;Cosenza:CS;Crotone:KR;Reggio Calabria:RC;Vibo Valentia:VV","Campania":"Avellino:AV;Benevento:BN;Caserta:CE;Napoli:NA;Salerno:SA","Emilia-Romagna":"Bologna:BO;Ferrara:FE;Forlì-Cesena:FC;Modena:MO;Parma:PR;Piacenza:PC;Ravenna:RA;Reggio nell'Emilia:RE;Rimini:RN","Friuli-Venezia Giulia":"Gorizia:GO;Pordenone:PN;Trieste:TS;Udine:UD","Lazio":"Frosinone:FR;Latina:LT;Rieti:RI;Roma:RM;Viterbo:VT","Liguria":"Genova:GE;Imperia:IM;La Spezia:SP;Savona:SV","Lombardia":"Bergamo:BG;Brescia:BS;Como:CO;Cremona:CR;Lecco:LC;Lodi:LO;Mantova:MN;Milano:MI;Monza e della Brianza:MB;Pavia:PV;Sondrio:SO;Varese:VA","Marche":"Ancona:AN;Ascoli Piceno:AP;Fermo:FM;Macerata:MC;Pesaro e Urbino:PU","Molise":"Campobasso:CB;Isernia:IS","Piemonte":"Alessandria:AL;Asti:AT;Biella:BI;Cuneo:CN;Novara:NO;Torino:TO;Verbano-Cusio-Ossola:VB;Vercelli:VC","Puglia":"Bari:BA;Barletta-Andria-Trani:BT;Brindisi:BR;Foggia:FG;Lecce:LE;Taranto:TA","Sardegna":"Cagliari:CA;Gallura Nord-Est Sardegna:OT;Medio Campidano:VS;Nuoro:NU;Ogliastra:OG;Oristano:OR;Sassari:SS;Sulcis Iglesiente:SU","Sicilia":"Agrigento:AG;Caltanissetta:CL;Catania:CT;Enna:EN;Messina:ME;Palermo:PA;Ragusa:RG;Siracusa:SR;Trapani:TP","Toscana":"Arezzo:AR;Firenze:FI;Grosseto:GR;Livorno:LI;Lucca:LU;Massa-Carrara:MS;Pisa:PI;Pistoia:PT;Prato:PO;Siena:SI","Trentino-Alto Adige/Südtirol":"Bolzano/Bozen:BZ;Trento:TN","Umbria":"Perugia:PG;Terni:TR","Valle d'Aosta/Vallée d'Aoste":"Valle d'Aosta/Vallée d'Aoste:AO","Veneto":"Belluno:BL;Padova:PD;Rovigo:RO;Treviso:TV;Venezia:VE;Verona:VR;Vicenza:VI"};
  const normalize = v => String(v||'').normalize('NFD')
    .replace(/[\u0300-\u036f]/g,'').replace(/[\u2018\u2019\u00b4]/g,"'")
    .replace(/[\u2010-\u2015-]/g,' ').replace(/\s+/g,' ').trim().toLocaleLowerCase('it-IT');
  // Google may expand a selected "Piazza" to "P.za" in formattedAddress.
  // Equivalent named streets are already present and must not be prepended twice.
  // Only these explicit, auditable abbreviations are canonicalized (no approximate matching).
  const normalizeStreetName = v => normalize(String(v||'')
    .replace(/\bp\s*\.\s*(?:zza|za)\b\.?/giu, 'piazza')
    .replace(/\bpiazza\b/giu, 'piazza'));
  const sameStreetInFormatted = (streetName,formatted) => {
    if (!streetName || !formatted) return false;
    const head=String(formatted).split(',')[0].trim();
    return normalizeStreetName(head)===normalizeStreetName(streetName);
  };
  const component = (components,type) => (components||[]).find(x=>(x.types||[]).includes(type));
  const variants = part => part ? [part.longText,part.shortText].filter(Boolean) : [];
  const canonicalVariants = name => [name,...String(name).split('/')].map(normalize);
  const administrativeName = value => normalize(value)
    .replace(/^(?:(?:provincia(?: autonoma)?|citta metropolitana|libero consorzio comunale)\s+(?:di|del|della|dello)\s+|provincia\s+dell')/u,'').trim();
  function resolveTerritory(components,registry) {
    const country=component(components,'country');
    if (!country||!variants(country).some(v=>['it','italia','italy'].includes(normalize(v)))) return {error:'outside_italy'};
    const entries=Array.isArray(registry)?registry:[];
    const byParent=new Map();
    for(const e of entries) {
      if(!Number.isSafeInteger(Number(e.id))||Number(e.id)<=0||!e.name)continue;
      for(const n of canonicalVariants(e.name).flatMap(x=>[x,...(x.startsWith("l'")?[x.slice(2)]:[]),...(x.startsWith('la ')?[x.slice(3)]:[])])) {
        const key=Number(e.parent)+':'+n;
        if(!byParent.has(key))byParent.set(key,new Map());
        byParent.get(key).set(Number(e.id),e);
      }
    }
    function matches(parent,names) {
      const matches=new Map();
      for(const n of names)for(const [id,e] of byParent.get(parent+':'+n)||[])matches.set(id,e);
      return [...matches.values()];
    }
    const regionCandidates=matches(0,variants(component(components,'administrative_area_level_1')).map(normalize));
    if(regionCandidates.length!==1)return {error:'unresolved_region'};
    const region=regionCandidates[0];
    const provincePart=component(components,'administrative_area_level_2');
    if(!provincePart)return {error:'unresolved_province',partial:{region:region.name}};
    const siglaMap=new Map((PROVINCE_SIGLE[region.name]||'').split(';').filter(Boolean).map(s=>{
      const i=s.lastIndexOf(':');return [normalize(s.slice(i+1)),s.slice(0,i)];
    }));
    // Google long and short variants are independent evidence. A recognized
    // provincial name or sigla belonging to ANOTHER region vetoes assignment.
    // Cross-region checking here is rejection-only, never global resolution.
    const allNames=new Map(),allCodes=new Map();
    for(const [r,items] of Object.entries(PROVINCE_SIGLE))
      for(const pair of items.split(';').filter(Boolean)){
        const i=pair.lastIndexOf(':'),label=pair.slice(0,i),code=normalize(pair.slice(i+1));
        for(const n of canonicalVariants(label)){
          if(!allNames.has(n))allNames.set(n,new Set());
          allNames.get(n).add(r);
        }
        if(!allCodes.has(code))allCodes.set(code,new Set());
        allCodes.get(code).add(r);
      }
    const longLabel=administrativeName(provincePart.longText||'');
    if(allNames.has(longLabel) && !allNames.get(longLabel).has(region.name))
      return {error:'unresolved_province',partial:{region:region.name}};
    const shortCode=normalize(provincePart.shortText||'');
    if(allCodes.has(shortCode) && !allCodes.get(shortCode).has(region.name))
      return {error:'unresolved_province',partial:{region:region.name}};
    const provinces=new Map();
    for(const value of variants(provincePart)){
      const norm=normalize(value),stripped=administrativeName(value);
      const names=new Set([norm,stripped]);
      if(stripped.startsWith("l'"))names.add(stripped.slice(2));
      if(siglaMap.has(norm))names.add(normalize(siglaMap.get(norm)));
      const one=matches(Number(region.id),[...names]);
      if(one.length>1)return {error:'unresolved_province',partial:{region:region.name}};
      if(one.length===1)provinces.set(Number(one[0].id),one[0]);
    }
    if(provinces.size!==1)return {error:provinces.size?'conflicting_province':'unresolved_province',partial:{region:region.name}};
    const province=[...provinces.values()][0];
    const municipalityParts=[component(components,'locality'),component(components,'administrative_area_level_3')].filter(Boolean);
    if(!municipalityParts.length)return {error:'unresolved_municipality',partial:{region:region.name,province:province.name}};
    const municipalities=new Map();
    for(const part of municipalityParts) {
      const one=matches(Number(province.id),variants(part).map(normalize));
      if(one.length>1)return {error:'unresolved_municipality'};
      if(one.length===1)municipalities.set(Number(one[0].id),one[0]);
    }
    if(municipalities.size!==1)return {error:municipalities.size?'conflicting_municipality':'unresolved_municipality',partial:{region:region.name,province:province.name}};
    const municipality=[...municipalities.values()][0];
    // Places may classify a settlement as locality while placing its parent
    // municipality in administrative_area_level_3. A locality component is
    // eligible as the canonical CHILD only after the municipality has been
    // uniquely resolved under the verified province. Never infer from the
    // formatted address, coordinates, or a global settlement-name match.
    const localNames=[
      component(components,'sublocality'),
      component(components,'sublocality_level_1'),
      component(components,'neighborhood'),
      component(components,'locality')
    ].filter(Boolean).flatMap(part=>variants(part).map(normalize));
    const localities=matches(Number(municipality.id),localNames);
    if(localities.length>1)return {error:'ambiguous_locality'};
    const locality=localities[0];
    return {country:'IT',
      location:{region:region.name,province:province.name,municipality:municipality.name,locality:locality?.name||''},
      termIds:{region:Number(region.id),province:Number(province.id),municipality:Number(municipality.id),...(locality?{locality:Number(locality.id)}:{})}};
  }
  // A proposal is a label, never an official administrative boundary or a POI.
  function resolveNeighborhood(components, municipality) {
    const parts=components||[];
    for(const type of ['neighborhood','sublocality_level_5','sublocality_level_4','sublocality_level_3','sublocality_level_2','sublocality_level_1','sublocality']){
      const labels=new Map();
      parts.filter(p=>(p.types||[]).includes(type)).forEach(p=>{
        const label=String(p.longText||p.long_name||'').trim();
        if(label && normalize(label)!==normalize(municipality))labels.set(normalize(label),label);
      });
      if(labels.size>1)return null;
      if(labels.size===1){const label=[...labels.values()][0];
        if([...label].length>100||!/[\p{L}]/u.test(label)||/[\r\n@<>]|https?:/iu.test(label))return null;
        return {label,source:type==='neighborhood'?'google_neighborhood':'google_sublocality',confirmed:false};
      }
    }
    return null;
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
    // PHP emits compact [term_id,parent_id,name] tuples to keep the initial
    // JSON smaller. Hydrate once per wizard mount; accept legacy object fixtures.
    const canonicalTerritories=(config.smartAddress?.territories||[]).map(entry=>
      Array.isArray(entry)?{id:entry[0],parent:entry[1],name:entry[2]}:entry);

    // No provider key: display the manual fallback rather than hiding the whole address workflow.
    const start = ui.querySelector('[data-smart-start]');
    const status = ui.querySelector('[data-smart-status]');
    const holder = ui.querySelector('[data-smart-widget]');
    const confirmation=ui.querySelector('[data-smart-confirmation]');
    const confirmationLabel=ui.querySelector('[data-smart-address-label]');
    const manualButton=ui.querySelector('[data-smart-manual]');
    const manualFields=container.querySelector('[data-native-location-fields]');
    let neighborhoodBox, neighborhoodInput, neighborhoodStatus, neighborhoodConfirm, neighborhoodClear;
    let zoneRevision=0, zoneTimer;
    const locked=()=>model.busy||!!model.snapshot||model.completed;
    if(config.neighborhoodProposal){
      neighborhoodBox=root.document.createElement('fieldset');neighborhoodBox.className='ba-native-neighborhood';neighborhoodBox.hidden=true;
      const legend=root.document.createElement('legend');legend.textContent='Quartiere o zona (facoltativo)';
      const label=root.document.createElement('label');label.htmlFor='ba-neighborhood-label';label.textContent='Verifica o inserisci il nome della zona';
      neighborhoodInput=root.document.createElement('input');neighborhoodInput.type='text';neighborhoodInput.id=label.htmlFor;neighborhoodInput.maxLength=100;
      neighborhoodInput.autocomplete='off';neighborhoodInput.setAttribute('aria-describedby','ba-neighborhood-status');
      neighborhoodStatus=root.document.createElement('p');neighborhoodStatus.id='ba-neighborhood-status';neighborhoodStatus.setAttribute('role','status');neighborhoodStatus.setAttribute('aria-live','polite');
      const actions=root.document.createElement('div');actions.className='ba-native-neighborhood__actions';
      neighborhoodConfirm=root.document.createElement('button');neighborhoodConfirm.type='button';neighborhoodConfirm.textContent='Conferma zona';
      neighborhoodClear=root.document.createElement('button');neighborhoodClear.type='button';neighborhoodClear.textContent='Non indicare una zona';
      actions.append(neighborhoodConfirm,neighborhoodClear);neighborhoodBox.append(legend,label,neighborhoodInput,neighborhoodStatus,actions);ui.append(neighborhoodBox);
      neighborhoodInput.addEventListener('input',()=>{if(locked())return;cancelZoneLookup();model.neighborhood=null;neighborhoodStatus.textContent='Zona proposta da te. Confermala per includerla nel riepilogo; sarà verificata in moderazione.';syncVisibility();});
      neighborhoodConfirm.addEventListener('click',()=>{
        if(locked())return;const label=neighborhoodInput.value.trim();
        if(!label||![...label].length||[...label].length>100||!/[\p{L}]/u.test(label)||/[\r\n@<>]|https?:/iu.test(label)){neighborhoodStatus.textContent='Inserisci solo il nome del quartiere o della zona, senza indirizzi o recapiti.';neighborhoodInput.focus();return;}
        const source=model.neighborhood?.label===label?model.neighborhood.source:'user_proposed';cancelZoneLookup();
        model.neighborhood={label,source,confirmed:true};neighborhoodStatus.textContent='Zona confermata: '+label+'. Sarà pubblicata solo dopo la verifica del moderatore.';syncVisibility();
      });
      neighborhoodClear.addEventListener('click',()=>{if(locked())return;clearNeighborhood();neighborhoodStatus.textContent='Nessuna zona indicata. Puoi continuare.';syncVisibility();});
    }
    function cancelZoneLookup(){zoneRevision++;root.clearTimeout(zoneTimer);if(neighborhoodStatus)neighborhoodStatus.removeAttribute('aria-busy');}
    function clearNeighborhood(){cancelZoneLookup();model.neighborhood=null;if(neighborhoodInput)neighborhoodInput.value='';if(neighborhoodStatus)neighborhoodStatus.textContent='Puoi indicare una zona oppure continuare senza.';}
    function setNeighborhood(proposal){
      if(!neighborhoodInput)return;model.neighborhood=proposal;neighborhoodInput.value=proposal?.label||'';
      neighborhoodStatus.textContent=proposal?'Google propone «'+proposal.label+'». Verifica il nome e premi Conferma zona. Non identifica confini ufficiali.':'Google non ha identificato una zona univoca. Puoi inserirla tu oppure continuare senza.';
      syncVisibility();
    }
    async function suggestNeighborhood(place,mapped,lat,lng,id){
      if(!neighborhoodBox)return;
      const direct=resolveNeighborhood(place.addressComponents,mapped.location.municipality);
      if(direct){setNeighborhood(direct);return;}
      setNeighborhood(null);
      if((place.addressComponents||[]).some(p=>(p.types||[]).some(t=>t==='neighborhood'||t.startsWith('sublocality'))))return;
      if(!Number.isFinite(lat)||!Number.isFinite(lng)||!root.google?.maps?.importLibrary)return;
      const revision=++zoneRevision;
      neighborhoodStatus.textContent='Ricerca del quartiere in corso… Puoi continuare anche senza.';neighborhoodStatus.setAttribute('aria-busy','true');
      const current=()=>!disposed&&id===requestId&&revision===zoneRevision&&!locked();
      zoneTimer=root.setTimeout(()=>{if(revision===zoneRevision){if(current())setNeighborhood(null);zoneRevision++;neighborhoodStatus.removeAttribute('aria-busy');}},7000);
      try{
        const lib=await root.google.maps.importLibrary('geocoding');if(!current())return;
        const response=await new lib.Geocoder().geocode({location:{lat,lng},language:'it'});if(!current())return;
        const components=[];
        for(const result of response.results||[]){
          const parts=(result.address_components||result.addressComponents||[]).map(p=>({types:p.types,longText:p.longText||p.long_name,shortText:p.shortText||p.short_name}));
          const resolved=resolveTerritory(parts,canonicalTerritories);
          if(resolved.location&&LEVELS.slice(0,3).every(k=>resolved.location[k]===mapped.location[k]))components.push(...parts);
        }
        setNeighborhood(resolveNeighborhood(components,mapped.location.municipality));
      }catch(_){if(current())setNeighborhood(null);}
      finally{if(revision===zoneRevision){root.clearTimeout(zoneTimer);neighborhoodStatus.removeAttribute('aria-busy');}}
    }
    let manual=false;
    function syncVisibility(){
      if(manualFields)manualFields.hidden=!manual;
      const choice=container.querySelector('[data-native-public-location-choice]');
      const ready=selected||(manual&&['region','province','municipality','exact_address'].every(key=>String(container.querySelector('[name="location.'+key+'"]')?.value||'').trim()));
      const layout=ui.querySelector('[data-smart-location-layout]');if(layout)layout.hidden=!ready;
      const card=ui.querySelector('[data-smart-location-card]');if(card)card.hidden=!selected;
      if(choice){choice.hidden=!ready;choice.querySelectorAll('input[type=radio]').forEach(input=>{input.disabled=!ready||model.busy||!!model.snapshot||model.completed;});
        const radius=choice.querySelector('[name="ba-public-location-radius"]');if(radius)radius.disabled=!ready||model.publicLocationRequest!=='approximate_area'||model.busy||!!model.snapshot||model.completed;}
      if(neighborhoodBox){neighborhoodBox.hidden=!ready;neighborhoodInput.disabled=!ready||locked();neighborhoodClear.disabled=!ready||locked();neighborhoodConfirm.disabled=!ready||locked()||!neighborhoodInput.value.trim()||!!model.neighborhood?.confirmed;neighborhoodConfirm.textContent=model.neighborhood?.confirmed?'Zona confermata':'Conferma zona';}
      mapView?.sync();
    }
    function offerManual(message){if(config.locationManualEntry===false){say('La ricerca del luogo non è disponibile per questo indirizzo. Riprova la ricerca o riprova più tardi.');if(confirmation)confirmation.hidden=true;return;}say(message+' Puoi utilizzare i campi manuali.');if(manualButton)manualButton.hidden=false;if(confirmation)confirmation.hidden=true;}
    const paths = LEVELS.map(v => 'location.' + v);
    const privatePaths = ['location.exact_address','location.exact_lat','location.exact_lng'];
    let disposed=false, selected=false, widget, requestId=0, applying=false;
    const mapView=ns.LocationMap?.mount(container,config,model,{
      load:()=>loadPlaces(config.smartAddress?.key),ticket:()=>requestId,
      current:id=>!disposed&&id===requestId&&!model.busy&&!model.snapshot&&!model.completed,
      // Acquiring a device position is speculative. Keep the last verified
      // address/pin until the browser actually returns a usable position.
      reserve(){return ++requestId;},
      begin(){invalidate('Verifica della posizione in corso…');clearLocation(true);return requestId;},
      async accept(place,position,id){
        if(disposed||id!==requestId||model.busy||model.snapshot||model.completed)return false;
        // The point was reverse-geocoded by Google. Address/territory are still
        // resolved from Place Details; no coordinates infer a territory locally.
        const verified={fetchFields:options=>place.fetchFields(options),
          get addressComponents(){return place.addressComponents;},get formattedAddress(){return place.formattedAddress;},
          location:{lat:()=>position.lat,lng:()=>position.lng}};
        await choose({placePrediction:{toPlace:()=>verified}});
        return selected;
      }
    });
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
      clearNeighborhood();
      selected=false;
      syncVisibility();
      mapView?.hide();
      if(confirmation)confirmation.hidden=true;
      // If a manually edited territory could disagree with the selected
      // street address, require the user to confirm/re-enter exact address.
      if (territoryChanged) assign('location.exact_address','');
      assign('location.exact_lat','');
      assign('location.exact_lng','');
      syncVisibility();say(reason);
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
      if (disposed || model.busy || model.snapshot || model.completed) return;
      const id=++requestId;
      clearNeighborhood();
      selected=false;
      syncVisibility();
      mapView?.hide();
      if(confirmation)confirmation.hidden=true;
      // Do not submit an old territory or exact coordinate while resolving a new Place.
      clearLocation(true);syncVisibility();
      try {
        const place=event.placePrediction?.toPlace?.();
        if (!place) throw new Error('place_unavailable');
        say('Verifica dell’indirizzo in corso…');
        await place.fetchFields({fields:['addressComponents','formattedAddress','location']});
        if (disposed || id!==requestId) return;
        const mapped=resolveTerritory(place.addressComponents,canonicalTerritories);
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
        const fullAddress=prefix && !normalize(formatted).includes(normalize(verifiedName)) && !sameStreetInFormatted(verifiedName,formatted)
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
        else if(selected){say('Posizione riconosciuta.');if(Number.isFinite(lat)&&Number.isFinite(lng))mapView?.show({lat,lng});void suggestNeighborhood(place,mapped,lat,lng,id);}
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
        widget.setAttribute('aria-label', 'Posizione dell’evento');
        widget.setAttribute('aria-describedby', 'ba-smart-privacy ba-smart-status');
        widget.addEventListener('gmp-select',choose);
        holder.replaceChildren(widget);
        start.hidden=true;holder.hidden=false;
        say('Cerca un indirizzo italiano e seleziona un suggerimento.');
        try{widget.focus()}catch(_){}
      } catch (_) {
        if (!disposed) {start.disabled=false;offerManual('Google non è disponibile. Inserisci la posizione manualmente.');}
      }
    }
    function openManual(){
      if(config.locationManualEntry===false)return;
      requestId++;clearNeighborhood();selected=false;manual=true;if(confirmation)confirmation.hidden=true;clearLocation(true);syncVisibility();
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
      requestId++;clearNeighborhood();selected=false;manual=false;
      if(confirmation)confirmation.hidden=true;
      mapView?.hide();
      if(confirmationLabel)confirmationLabel.textContent='';
      // A new report must not retain free text or prediction state inside the
      // Google custom element. Recreate it lazily on the next explicit focus.
      if(widget){widget.removeEventListener('gmp-select',choose);holder.replaceChildren();widget=null;}
      holder.hidden=true;
      start.value='';start.hidden=false;start.disabled=false;
      if(manualButton)manualButton.hidden=config.locationManualEntry===false || Boolean(config.smartAddress?.key);
      syncVisibility();
      say('Seleziona il campo per cercare un luogo.');
    }
    function changedGoogle(event){
      if(event.type==='gmp-select')return;
      invalidate('Indirizzo modificato: seleziona di nuovo un suggerimento.',true);
    }
    // Allow the widget's own keyboard selection, but cancel implicit form submit.
    // Free text never becomes a confirmed place: only gmp-select + fetchFields do.
    function searchKeydown(event){if(event.key==='Enter')event.preventDefault();}
    holder.addEventListener('keydown',searchKeydown);
    holder.addEventListener('input',changedGoogle);
    holder.addEventListener('change',changedGoogle);
    start.addEventListener('focus',activate);
    start.addEventListener('click',activate);
    manualButton?.addEventListener('click',openManual);
    if(!config.smartAddress?.key)offerManual('Google non configurato. Inserisci la posizione manualmente.');
    syncVisibility();
    return {canAdvance,syncVisibility,reset,dispose(){disposed=true;requestId++;cancelZoneLookup();neighborhoodBox?.remove();mapView?.dispose();holder.removeEventListener('keydown',searchKeydown);start.removeEventListener('focus',activate);start.removeEventListener('click',activate);manualButton?.removeEventListener('click',openManual);container.removeEventListener('input',changed);holder.removeEventListener('input',changedGoogle);holder.removeEventListener('change',changedGoogle);widget?.removeEventListener('gmp-select',choose);}};
  }
  ns.SmartAddress = {resolveTerritory,resolveNeighborhood,loadPlaces,mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = {resolveTerritory,resolveNeighborhood};
})(typeof window !== 'undefined' ? window : globalThis);
