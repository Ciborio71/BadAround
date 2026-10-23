'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),cp=require('node:child_process');
const {JSDOM}=require('jsdom');
const root='wordpress/themes/badaround-child/';
test('one reusable policy and four accessible viewport-only pan actions, including resized/fullscreen dimensions',()=>{
 const dom=new JSDOM('<form><div id="map"></div><button id="next">Avanti</button></form>',{runScripts:'outside-only'}),w=dom.window,canvas=w.document.getElementById('map');
 let width=320,height=340;Object.defineProperties(canvas,{clientWidth:{get:()=>width},clientHeight:{get:()=>height}});
 w.google={maps:{ControlPosition:{RIGHT_TOP:3}}};w.eval(fs.readFileSync(root+'assets/js/map-navigation.js','utf8'));
 const policy=w.BadAroundMapNavigation.options();
 for(const key of ['zoomControl','fullscreenControl','draggable','keyboardShortcuts'])assert.equal(policy[key],true,key);
 assert.equal(policy.disableDefaultUI,false);assert.equal(policy.cameraControl,false);assert.equal(policy.gestureHandling,'cooperative');
 assert.equal(policy.zoomControlOptions.position,3);assert.equal(policy.fullscreenControlOptions.position,3);
 const calls=[],map={controls:{3:{push:e=>canvas.append(e)}},panBy:(x,y)=>calls.push([x,y])};
 const control=w.BadAroundMapNavigation.mount(map,canvas);assert.equal(control,w.BadAroundMapNavigation.mount(map,canvas));
 assert.equal(canvas.querySelectorAll('[role=group]').length,1);
 const buttons=[...control.querySelectorAll('button')];assert.deepEqual(buttons.map(b=>b.getAttribute('aria-label')),['Sposta mappa verso nord','Sposta mappa verso sud','Sposta mappa verso ovest','Sposta mappa verso est']);
 buttons.forEach(b=>{assert.equal(b.type,'button');b.click();});assert.deepEqual(calls,[[0,-85],[0,85],[-80,0],[80,0]]);
 width=1440;height=900;buttons[3].click();assert.deepEqual(calls.at(-1),[360,0]);
 assert.equal(w.document.querySelectorAll('script').length,0);dom.window.close();
});
test('runtime scope is presentation-only and all semantic/backend bytes equal certified GEO-AREA composite',()=>{
 const allowed=[root+'functions.php',root+'assets/js/map.js',root+'assets/js/map-navigation.js',root+'assets/js/native-report/location-map.js',root+'assets/css/map-navigation.css'];
 const changed=cp.execFileSync('git',['diff','--name-only','879f55853fbb99c20ad85bc522e03f19f93bc3c6','--','wordpress/'],{encoding:'utf8'}).trim().split('\n').filter(Boolean);
 assert(changed.every(p=>allowed.includes(p)),changed.join('\n'));
 for(const p of allowed.filter(p=>!p.includes('map-navigation.')))assert(changed.includes(p));
 const functions=fs.readFileSync(root+'functions.php','utf8');assert.match(functions,/\$is_map \|\| \$is_home \|\| \$is_event \|\| \$is_reporting/);
 assert.match(functions,/\$previous = array\( 'badaround-map-navigation' \)/);
 assert.equal((functions.match(/assets\/js\/map-navigation\.js/g)||[]).length,2); // URL and filemtime, one enqueue.
 const nav=fs.readFileSync(root+'assets/js/map-navigation.js','utf8');assert.doesNotMatch(nav,/setInterval|setTimeout|fetch\(|new (?:google\.maps\.Map|GoogleMap)\(|\.setCenter\(|\.setRadius\(|exact_lat|public_location_request/);
 const inventory=cp.execFileSync('git',['grep','-n','-E','new (google\\.maps\\.Map|GoogleMap)\\(','--','wordpress/'],{encoding:'utf8'}).trim().split('\n');assert.equal(inventory.length,3);
 const admin=fs.readFileSync('wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php','utf8');assert.doesNotMatch(admin,/new .*Map\(|maps\.googleapis|<iframe/);
});
