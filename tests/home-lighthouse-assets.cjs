'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),{JSDOM}=require('jsdom');
const root=__dirname+'/../wordpress/themes/badaround-child/',css=fs.readFileSync(root+'assets/css/home.css','utf8');
const rgb=x=>[1,3,5].map(i=>parseInt(x.slice(i,i+2),16)/255);
const mix=(a,b,t)=>a.map((x,i)=>x*t+b[i]*(1-t));
const luminance=a=>a.reduce((sum,v,i)=>sum+[.2126,.7152,.0722][i]*(v<=.04045?v/12.92:((v+.055)/1.055)**2.4),0);
const contrast=(a,b)=>{const [x,y]=[luminance(a),luminance(b)].sort((a,b)=>a-b);return (y+.05)/(x+.05);};
test('all category card links and badges meet 4.5:1 with the actual scoped palette',()=>{
 const categories=[...css.matchAll(/--ba-card-accent:(#[0-9a-f]{6})\}/gi)].map(x=>x[1]);assert(categories.length>=6);
 const link=css.match(/\.ba-home-events \.ba-event-card--home \.ba-event-card__link\{color:color-mix\(in srgb,var\(--ba-card-accent\) (\d+)%,(#[0-9a-f]{6})\)\}/i);assert(link);
 for(const c of categories){const color=mix(rgb(c),rgb(link[2]),Number(link[1])/100);assert(contrast(color,rgb('#ffffff'))>=4.5,c+' link');assert(contrast(color,mix(rgb(c),rgb('#ffffff'),.11))>=4.5,c+' badge');}
 assert(contrast(rgb('#087f8c'),rgb('#ffffff'))>=4.5,'primary buttons');assert(contrast(rgb('#066f79'),rgb('#ffffff'))>=4.5,'hover buttons');
});
test('shared placeholder is a tiny local passive SVG with no external dependencies',()=>{
 const file=fs.readFileSync(root+'assets/event-placeholder.svg');assert(file.length<4096);
 const d=new JSDOM(file.toString(),{contentType:'image/svg+xml'});assert.equal(d.window.document.querySelectorAll('script,image,foreignObject,a').length,0);
 assert.equal(d.window.document.documentElement.getAttribute('viewBox'),'0 0 360 240');
 const fn=fs.readFileSync(root+'functions.php','utf8');assert(fn.includes("'/assets/event-placeholder'"));assert(!fn.includes('placeholder-segnalazione.png'));d.window.close();
});

test('six category placeholders are bounded passive local SVGs',()=>{
 for(const category of ['veicoli','case-e-attivita','pericoli','spazi-pubblici','animali','oggetti-e-documenti']){
  const file=fs.readFileSync(root+'assets/event-placeholder-'+category+'.svg');assert(file.length<4096);
  const d=new JSDOM(file.toString(),{contentType:'image/svg+xml'});assert.equal(d.window.document.querySelectorAll('script,image,foreignObject,a').length,0);assert.equal(d.window.document.documentElement.getAttribute('viewBox'),'0 0 360 240');assert.match(d.window.document.querySelector('text').textContent,/BadAround/);d.window.close();
 }
});
