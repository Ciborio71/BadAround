'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),{JSDOM}=require('jsdom');
const source=fs.readFileSync(__dirname+'/../wordpress/themes/badaround-child/assets/js/map.js','utf8');
const setup=source.slice(source.indexOf('const setupPlacesAutocomplete ='),source.indexOf('const setupHomePlaceSearch ='));
const tick=()=>new Promise(r=>setImmediate(r));
function fixture(importer){
 const dom=new JSDOM('<form><input><p>Contenuti pubblici</p></form>',{url:'https://example.test',runScripts:'outside-only'}),w=dom.window;
 let imports=0,task;w.console.warn=()=>{};w.setTimeout=fn=>{task=fn;return 1;};w.clearTimeout=()=>{task=null;};
 w.google={maps:{importLibrary:name=>{assert.equal(name,'places');imports++;return importer();}}};
 w.eval(setup+';window.mountPlaces=setupPlacesAutocomplete;');
 const input=w.document.querySelector('input'),selected=[];
 return {dom,w,input,selected,imports:()=>imports,mount:()=>w.mountPlaces(input,d=>selected.push(d)),run:()=>{const fn=task;task=null;return fn?.();},type:value=>{input.value=value;input.dispatchEvent(new w.Event('input'));}};
}
function library(fetch){return {AutocompleteSessionToken:class{},AutocompleteSuggestion:{fetchAutocompleteSuggestions:fetch}};}
function suggestion(){return {placePrediction:{mainText:{text:'Via Roma'},secondaryText:{text:'Roma'},toPlace:()=>({id:'public-place',formattedAddress:'Via Roma, Roma',location:{lat:()=>41.9,lng:()=>12.5},fetchFields:async()=>{}})}};}
test('Places is absent on entry, then imported once on focus; predictions and selection survive delayed loading',async()=>{
 let provide,calls=0;const x=fixture(()=>new Promise(r=>provide=r));await x.mount();assert.equal(x.imports(),0);
 x.input.focus();x.type('Via Roma');const pending=x.run();assert.equal(x.imports(),1);
 provide(library(async()=>{calls++;return {suggestions:[suggestion()]};}));await pending;await tick();
 assert.equal(calls,1);assert.equal(x.w.document.querySelectorAll('[role=option]').length,1);
 x.w.document.querySelector('[role=option]').click();await tick();assert.equal(x.selected[0].placeId,'public-place');assert.equal(x.input.value,'Via Roma, Roma');
 x.input.blur();x.input.focus();assert.equal(x.imports(),1);x.dom.window.close();
});
test('clearing search while a prediction is pending cannot reopen stale results',async()=>{
 let respond;const x=fixture(async()=>library(()=>new Promise(r=>respond=r)));await x.mount();x.input.focus();await tick();
 x.type('Via Roma');const request=x.run();await tick();x.type('');respond({suggestions:[suggestion()]});await request;await tick();
 assert.equal(x.w.document.querySelectorAll('[role=option]').length,0);assert.equal(x.w.document.querySelector('[role=listbox]').hidden,true);x.dom.window.close();
});
test('failed library loading can retry on the next focus without blocking fallback search',async()=>{
 let failed=true;const x=fixture(()=>failed?Promise.reject(Error('offline')):Promise.resolve(library(async()=>({suggestions:[suggestion()]}))));await x.mount();
 x.input.focus();await tick();assert.equal(x.imports(),1);assert.equal(x.w.document.querySelector('p').textContent,'Contenuti pubblici');
 failed=false;x.input.blur();x.input.focus();await tick();x.type('Roma');await x.run();assert.equal(x.imports(),2);assert.equal(x.w.document.querySelectorAll('[role=option]').length,1);x.dom.window.close();
});
