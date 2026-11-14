const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const {JSDOM}=require('jsdom');
const source=fs.readFileSync(__dirname+'/../wordpress/themes/badaround-child/assets/js/home-proximity.js','utf8');
const tick=()=>new Promise(r=>setTimeout(r,10));
function setup(permission){
 const dom=new JSDOM('<button data-ba-geolocate></button><div data-ba-home-feed>Italia</div><p data-ba-home-feed-status></p><p data-ba-geolocation-status></p>',{runScripts:'outside-only',url:'https://badaround.it/'});const w=dom.window;let calls=0,success,requests=[];
 Object.defineProperty(w.navigator,'permissions',{value:{query:async()=>({state:permission})}});
 Object.defineProperty(w.navigator,'geolocation',{value:{getCurrentPosition(fn){calls++;success=fn;}}});
 w.BadAroundMap={homeEndpoint:'/wp-json/badaround/v1/discovery/home'};
 w.google={maps:{importLibrary:async()=>({Geocoder:class{async geocode(){return {results:[]};}}})}};
 w.fetch=async(url,opts)=>{requests.push(JSON.parse(opts.body));return {ok:true,json:async()=>({count:1,html:'<article>Locale</article>',scopes:['nearby'],areas:{}})};};
 w.eval(source);return {w,dom,calls:()=>calls,success:(p)=>success({coords:{latitude:p.lat,longitude:p.lng}}),requests};
}
test('first visit never prompts, explicit click requests and updates public feed',async()=>{const x=setup('prompt');await tick();assert.equal(x.calls(),0);x.w.document.querySelector('button').click();assert.equal(x.calls(),1);x.success({lat:41.9,lng:12.5});await tick();assert.equal(x.requests.length,1);assert.match(x.w.document.querySelector('[data-ba-home-feed]').textContent,/Locale/);assert.equal(x.w.localStorage.length,0);assert.equal(x.w.sessionStorage.length,0);x.dom.window.close();});
test('already granted permission reuses geolocation',async()=>{const x=setup('granted');await tick();assert.equal(x.calls(),1);x.dom.window.close();});
test('manual selection wins over an older pending geolocation',async()=>{const x=setup('prompt');x.w.document.querySelector('button').click();x.w.document.dispatchEvent(new x.w.CustomEvent('badaround:home-area-selected',{detail:{position:{lat:45,lng:9}}}));x.success({lat:41.9,lng:12.5});await tick();assert.equal(x.requests.length,1);assert.equal(x.requests[0].position.lat,45);x.dom.window.close();});
test('service failure removes national cards rather than presenting them as local',async()=>{const x=setup('prompt');x.w.fetch=async()=>({ok:false});x.w.document.dispatchEvent(new x.w.CustomEvent('badaround:home-area-selected',{detail:{position:{lat:45,lng:9}}}));await tick();assert.equal(x.w.document.querySelector('[data-ba-home-feed]').textContent,'');assert.match(x.w.document.querySelector('[data-ba-home-feed-status]').textContent,/Non è stato possibile/);x.dom.window.close();});
