'use strict';
// Offline Google control/API contract seam. Not evidence of live Google UI.
module.exports=function installProvider(){
 window.mapControlsProvider=(map,canvas,o)=>{
  canvas.style.position='relative';canvas.style.background='#e4f0eb';
  const viewport=document.createElement('div');viewport.tabIndex=0;viewport.role='region';viewport.setAttribute('aria-label','Mappa provider simulato');viewport.style.cssText='position:absolute;inset:0;touch-action:pan-y';canvas.append(viewport);
  const rail=document.createElement('div');rail.dataset.testControls='';rail.style.cssText='position:absolute;top:0;right:0;display:flex;align-items:end;flex-direction:column;z-index:2';canvas.append(rail);
  const button=(label,text,fn)=>{const b=document.createElement('button');b.type='button';b.setAttribute('aria-label',label);b.textContent=text;b.style.cssText='background:white;color:#16324f;border:1px solid #ccc;width:44px;height:44px;min-height:44px;padding:0;margin:0 10px;cursor:pointer;font-size:20px';b.onclick=fn;rail.append(b);return b;};
  if(o.fullscreenControl&&document.fullscreenEnabled)button('Schermo intero (provider simulato)','⛶',()=>document.fullscreenElement?document.exitFullscreen():canvas.requestFullscreen());
  if(o.zoomControl){button('Aumenta zoom (provider simulato)','+',()=>map.setZoom(map.getZoom()+1));button('Riduci zoom (provider simulato)','−',()=>map.setZoom(map.getZoom()-1));}
  const attribution=document.createElement('div');attribution.dataset.testAttribution='';attribution.textContent='Attribuzione provider simulato — non Google live';attribution.style.cssText='position:absolute;bottom:0;right:0;font:9px system-ui;background:white;z-index:3';canvas.append(attribution);
  map.options=o;map.panCalls=[];map.viewport={x:0,y:0};
  map.panBy=(x,y)=>{map.panCalls.push([x,y]);map.viewport.x+=x;map.viewport.y+=y;};
  let start;
  viewport.addEventListener('pointerdown',e=>{start={x:e.clientX,y:e.clientY};viewport.setPointerCapture(e.pointerId);});
  viewport.addEventListener('pointerup',e=>{if(start&&o.draggable)map.panBy(start.x-e.clientX,start.y-e.clientY);start=null;});
  viewport.addEventListener('keydown',e=>{const xy={ArrowUp:[0,-50],ArrowDown:[0,50],ArrowLeft:[-50,0],ArrowRight:[50,0]}[e.key];if(xy&&o.keyboardShortcuts){e.preventDefault();map.panBy(...xy);}});
  return {RIGHT_TOP:{push:el=>{rail.append(el);return rail.children.length;}}};
 };
};
