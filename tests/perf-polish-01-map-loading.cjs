'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/map.js'),'utf8');

test('discovery renders before Google Maps bootstrap',()=>{
  const discovery=source.indexOf('payload = await loadDiscovery()');
  const render=source.indexOf('renderList(items, total)',discovery);
  const schedule=source.indexOf('scheduleMaps(async () =>',render);
  const maps=source.indexOf('await loadGoogleMaps()',schedule);
  assert(discovery>=0 && render>discovery && schedule>render && maps>schedule);
});

test('only Home map bootstrap is deferred; full/detail contexts remain immediate',()=>{
  assert(source.includes("mapNodes.every((node) => (node.dataset.baMapContext || 'full') === 'home')"));
  assert(source.includes("if (!homeOnly) { run(); return; }"));
  assert(source.includes("window.addEventListener('load', start, { once: true })"));
});

test('provider failure still initializes public fallback/status path without mutating GEO data',()=>{
  assert(source.includes("catch (error) { console.error('BadAround map:', error); }"));
  assert(source.includes('mapNodes.forEach((node) => initMap(node, items))'));
  assert(!source.includes('location.exact_lat'));
  assert(!source.includes('location.exact_lng'));
});
