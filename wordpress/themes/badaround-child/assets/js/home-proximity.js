(() => {
    'use strict';
    const button = document.querySelector('[data-ba-geolocate]');
    const feed = document.querySelector('[data-ba-home-feed]');
    const status = document.querySelector('[data-ba-home-feed-status]');
    const geoStatus = document.querySelector('[data-ba-geolocation-status]');
    if (!button || !feed || !status) return;
    let revision = 0, current = null, controller = null;
    const tell = (message) => { if (geoStatus) geoStatus.textContent = message; };
    const valid = (p) => p && Number.isFinite(p.lat) && Number.isFinite(p.lng) && Math.abs(p.lat) <= 90 && Math.abs(p.lng) <= 180;
    const showMap = () => { if (current) document.dispatchEvent(new CustomEvent('badaround:home-area', {detail:current})); };
    document.addEventListener('badaround:home-map-ready', showMap);
    async function areasFor(position) {
        if (!window.google?.maps?.importLibrary) await new Promise((resolve) => {
            const finish = () => { clearTimeout(timer);document.removeEventListener('badaround:home-map-ready',finish);resolve(); };
            const timer = setTimeout(finish,12000);
            document.addEventListener('badaround:home-map-ready',finish,{once:true});
        });
        if (!window.google?.maps?.importLibrary) return {};
        const {Geocoder} = await google.maps.importLibrary('geocoding');
        const result = await new Geocoder().geocode({location:position, language:'it'});
        const components = (result.results || []).flatMap((r) => r.address_components || []);
        const find = (type) => components.find((c) => c.types?.includes(type))?.long_name || '';
        if (components.find((c) => c.types?.includes('country'))?.short_name !== 'IT') return {};
        return {region:find('administrative_area_level_1'), province:find('administrative_area_level_2'), municipality:find('administrative_area_level_3') || find('locality')};
    }
    async function select(position, source) {
        if (!valid(position)) return;
        const mine = ++revision;
        controller?.abort();controller = new AbortController();
        const signal = controller.signal;
        current = {position, source}; showMap();
        // Remove the national/previous-area list immediately; never label it as local during loading/failure.
        feed.replaceChildren();feed.setAttribute('aria-busy','true');
        status.textContent = 'Cerchiamo le segnalazioni vicine e nelle aree circostanti…';
        try {
            let areas = {};
            try { areas = await areasFor(position); } catch (_) { /* Nearby public coordinates remain usable. */ }
            if (mine !== revision) return;
            const endpoint = window.BadAroundMap?.homeEndpoint;
            if (!endpoint) throw new Error('Discovery unavailable');
            const response = await fetch(endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',signal,
                headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({position,areas})});
            if (!response.ok) throw new Error('Discovery unavailable');
            const data = await response.json();
            if (mine !== revision) return;
            feed.innerHTML = data.html || ''; // Only server-rendered, escaped public card templates.
            const labels = {nearby:'entro 5 km',municipality:'nel comune',province:'nella provincia',region:'nella regione'};
            const scopes = (data.scopes || []).map((s) => labels[s]).filter(Boolean);
            status.textContent = data.count ? `Segnalazioni ${scopes.join(', ')}. Le più vicine hanno precedenza; ogni card indica l’area.` :
                'Non risultano segnalazioni pubblicate nelle vicinanze o nei territori riconosciuti. Prova a cercare un’altra zona.';
            if (!data.areas?.region) status.textContent += ' Il territorio non è stato riconosciuto: mostriamo solo eventuali posizioni pubbliche entro 5 km.';
        } catch (error) {
            if (mine === revision && error.name !== 'AbortError') status.textContent = 'Non è stato possibile aggiornare le segnalazioni della zona. Riprova o usa la ricerca manuale.';
        } finally { if (mine === revision) feed.removeAttribute('aria-busy'); }
    }
    function locate() {
        if (!navigator.geolocation) { tell('La geolocalizzazione non è disponibile. Usa la ricerca manuale.');return; }
        button.disabled = true;tell('Richiesta della posizione in corso…');
        const requestedAt = revision;
        navigator.geolocation.getCurrentPosition((result) => {
            button.disabled = false;
            if (requestedAt !== revision) return;
            tell('Posizione disponibile. La usiamo per questa esplorazione, senza salvarla in BadAround.');
            select({lat:Number(result.coords.latitude),lng:Number(result.coords.longitude)},'geolocation');
        },(error) => {
            button.disabled = false;
            tell(error.code === 1 ? 'Permesso posizione non concesso. Puoi cercare manualmente un comune, un quartiere o un indirizzo.' : 'Posizione non disponibile. Riprova o usa la ricerca manuale.');
        },{enableHighAccuracy:false,timeout:10000,maximumAge:60000});
    }
    button.addEventListener('click',locate);
    document.addEventListener('badaround:home-area-selected',(event) => select(event.detail?.position,'manual'));
    // Never trigger a first-time browser prompt on entry; only reuse an already granted permission.
    if (navigator.permissions?.query) navigator.permissions.query({name:'geolocation'}).then((permission) => {
        if (permission.state === 'granted' && !current) locate();
    }).catch(() => {});
})();
