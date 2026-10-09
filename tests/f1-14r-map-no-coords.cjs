'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const source = fs.readFileSync(
  path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/map.js'),
  'utf8'
);

const tick = () => new Promise(resolve => setImmediate(resolve));

test('F1.14R published territory-only event remains listed and creates no map marker', async () => {
  const dom = new JSDOM(`<!doctype html><html><body>
    <aside data-ba-map-results>
      <button data-ba-map-close></button>
      <span data-ba-map-count></span>
      <div data-ba-map-list></div>
    </aside>
    <button data-ba-map-open></button>
    <span data-ba-map-mobile-count></span>
    <div data-ba-map-backdrop hidden></div>
    <div class="wrap">
      <div data-ba-map data-ba-map-context="full"></div>
      <div data-ba-map-status hidden></div>
    </div>
  </body></html>`, {
    url:'https://staging.badaround.it/mappa/',
    runScripts:'outside-only'
  });

  const {window} = dom;
  let markers = 0;
  const errors = [];

  class MapMock {
    constructor(){ this.zoom=7; }
    addListener(){}
    getZoom(){ return this.zoom; }
    setZoom(v){ this.zoom=v; }
    setCenter(){}
    fitBounds(){}
    panTo(){}
  }
  class BoundsMock {
    extend(){}
    getCenter(){ return {lat:41.9,lng:12.5}; }
  }
  class OverlayMock {
    setMap(){ if (typeof this.onAdd==='function') this.onAdd(); if (typeof this.draw==='function') this.draw(); }
    getProjection(){ return null; }
  }
  class MarkerMock {
    constructor(){ markers++; }
    addListener(){}
    setMap(){}
    setIcon(){}
    setZIndex(){}
    setPosition(){}
  }
  class CircleMock { constructor(){} }
  class InfoWindowMock { setContent(){} open(){} close(){} }
  class SizeMock { constructor(){} }
  class PointMock { constructor(){} }
  class LatLngMock { constructor(value){ Object.assign(this,value); } }

  window.google = {maps:{
    Map:MapMock,
    LatLngBounds:BoundsMock,
    OverlayView:OverlayMock,
    Marker:MarkerMock,
    Circle:CircleMock,
    InfoWindow:InfoWindowMock,
    Size:SizeMock,
    Point:PointMock,
    LatLng:LatLngMock
  }};

  window.BadAroundMap = {
    endpoint:'/wp-json/badaround/v1/discovery',
    limit:100,
    hasMaps:true,
    mapsUrl:'/google-maps.js',
    categoryByTerm:{'11':'veicoli'},
    placeholder:''
  };

  window.fetch = async () => ({
    ok:true,
    json:async () => ({
      total:1,
      items:[{
        id:269,
        title:'Veicolo rubato a Torvaianica',
        permalink:'https://staging.badaround.it/evento-269/',
        excerpt:'Segnalazione pubblica.',
        event_type:{id:11,name:'Veicolo rubato',slug:'veicolo-rubato'},
        territory:{id:5,name:'Torvaianica',slug:'torvaianica'},
        occurred_date:'',
        occurred_time:'',
        event_status:'open',
        public_place_name:'Torvaianica',
        public_geo:null
      }]
    })
  });

  window.console.error = (...args) => errors.push(args);
  window.eval(source);
  await tick();
  await tick();

  assert.equal(markers,0,'coordinate-less event must not create a map marker');
  const cards = window.document.querySelectorAll('[data-ba-map-list] [data-ba-event-id="269"]');
  assert.equal(cards.length,1,'coordinate-less published event remains available in discovery list');
  assert.match(
    window.document.querySelector('[data-ba-map-status]').textContent,
    /non dispongono ancora di una posizione pubblica cartografabile/i
  );
  assert.equal(errors.length,0,'coordinate-less event must not generate JS errors');

  dom.window.close();
});
