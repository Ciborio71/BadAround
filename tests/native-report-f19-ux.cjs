'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {create,tick}=require('./native-media-dom-harness.cjs');

const cssPath=path.join(__dirname,'../wordpress/themes/badaround-child/assets/css/native-report.css');
const css=fs.readFileSync(cssPath,'utf8');

test('F1.9 typography and Astra isolation stay scoped to native wizard',()=>{
  assert(css.includes('--ba-native-font:'));
  assert(css.includes('.ba-native :where(h1,h2,h3,p,label,legend,input,select,textarea,button,small,dt,dd)'));
  const f19=css.slice(css.indexOf('/* F1.9')).replace(/^\/\*[\s\S]*?\*\//,'').trim();
  for(const forbidden of [
    /(^|\n)\s*html\s*\{/,
    /(^|\n)\s*body(?:[\s.{:#]|$)/,
    /(^|\n)\s*(?:input|select|textarea|button|label|fieldset|h1|h2|h3|p)\s*\{/
  ]) assert(!forbidden.test(f19),'F1.9 introduces an unscoped global selector');
  // Native location's canonical hidden fields must remain invisible even when
  // Astra / third-party styles attempt to override the HTML hidden attribute.
  // Allow only the canonical privacy fence and the approved visually-hidden live status.
  const importantRules=f19.split('}').filter(rule=>rule.includes('!important')).map(rule=>rule.trim());
  assert.equal(importantRules.length,2);
  assert(importantRules.some(rule=>rule.includes('.ba-native [data-native-location-fields][hidden]{display:none!important')));
  assert(importantRules.some(rule=>rule.includes('.ba-native-conditional-status{position:absolute!important')&&rule.includes('clip-path:inset(50%)')&&rule.includes('width:1px')&&rule.includes('height:1px')));
});

test('F1.9 step transition focuses heading and scrolls only when step is outside safe viewport',()=>{
  const h=create();
  h.fill();
  const target=h.doc.querySelector('[data-native-step="location"]');
  target.getBoundingClientRect=()=>({top:900,bottom:1200,left:0,right:0,width:800,height:300});
  let scrollArg=null;
  h.w.scrollTo=(arg)=>{scrollArg=arg;};
  Object.defineProperty(h.w,'innerHeight',{configurable:true,value:700});
  h.click('next');
  assert.equal(h.model.step,1);
  assert.equal(h.doc.activeElement.id,'ba-native-heading-location');
  assert(scrollArg && typeof scrollArg==='object');
  assert.equal(typeof scrollArg.top,'number');
  h.close();
});

test('F1.9 visible next step receives focus without forced scroll',()=>{
  const h=create();
  h.fill();
  const target=h.doc.querySelector('[data-native-step="location"]');
  target.getBoundingClientRect=()=>({top:120,bottom:500,left:0,right:0,width:800,height:380});
  let calls=0;
  h.w.scrollTo=()=>{calls++;};
  Object.defineProperty(h.w,'innerHeight',{configurable:true,value:800});
  h.click('next');
  assert.equal(h.doc.activeElement.id,'ba-native-heading-location');
  assert.equal(calls,0);
  h.close();
});

test('F1.9 validation error focuses summary, links field message and does not advance',()=>{
  const h=create();
  h.click('next');
  assert.equal(h.model.step,0);
  const summary=h.doc.querySelector('[data-native-errors]');
  assert.equal(summary.hidden,false);
  assert.equal(h.doc.activeElement,summary);
  const invalid=h.doc.querySelector('[data-native-field="event.category"] [data-native-input]');
  assert.equal(invalid.getAttribute('aria-invalid'),'true');
  const error=h.doc.querySelector('#ba-native-event-category-error');
  assert.equal(error.hidden,false);
  assert(error.textContent.length>0);
  h.close();
});

test('F1.9 hidden conditional field remains disabled and cannot receive focus',()=>{
  const h=create();
  const hidden=Array.from(h.doc.querySelectorAll('[data-native-field]')).find(w=>w.hidden);
  assert(hidden,'expected at least one progressive-disclosure hidden field');
  const input=hidden.querySelector('[data-native-input]');
  assert(input.disabled);
  input.focus();
  assert.notEqual(h.doc.activeElement,input);
  h.close();
});

test('F1.9 review is grouped by existing step metadata without changing model payload',()=>{
  const h=create();
  h.fill();
  h.goReview();
  const payloadBefore=JSON.stringify(h.model.payload());
  const review=h.doc.querySelector('[data-native-review]');
  const sections=review.querySelectorAll('.ba-native-review-section');
  assert(sections.length>=4);
  for(const section of sections){
    assert(section.querySelector('h3'));
    assert(section.querySelector('dl') || section.querySelector('.ba-native-review-media'));
  }
  assert(review.querySelectorAll('dt').length>0);
  assert(review.querySelectorAll('dd').length>0);
  assert(review.querySelectorAll('.ba-native-review-help').length>0);
  assert.equal(JSON.stringify(h.model.payload()),payloadBefore);
  h.close();
});

test('F1.9 navigation controls keep coherent touch targets and non-overflow responsive contracts',()=>{
  for(const needle of [
    '.ba-native .ba-native-actions .ba-button',
    'min-height:50px',
    '@media(max-width:1024px)',
    '@media(max-width:768px)',
    '@media(max-width:600px)',
    '@media(max-width:390px)',
    'grid-template-columns:repeat(2,minmax(0,1fr))',
    'grid-template-columns:1fr'
  ]) assert(css.includes(needle),needle);
  const h=create();
  const buttons=[h.doc.querySelector('[data-native-back]'),h.doc.querySelector('[data-native-next]'),h.doc.querySelector('[data-native-submit]')];
  buttons.forEach(b=>assert(b.classList.contains('ba-button')));
  h.close();
});

test('F1.9 choice controls remain native labels with keyboard-focusable inputs',()=>{
  const h=create();
  h.fill();
  while(h.model.step<4) h.click('next');
  const choice=Array.from(h.doc.querySelectorAll('.ba-native-choice')).find(label=>{
    const step=label.closest('[data-native-step]');
    const input=label.querySelector('input');
    return step && !step.hidden && input && !input.disabled;
  });
  assert(choice && choice.tagName==='LABEL');
  const input=choice.querySelector('input');
  input.focus();
  assert.equal(h.doc.activeElement,input);
  if(input.type==='checkbox'){
    input.checked=!input.checked;
    input.dispatchEvent(new h.w.Event('change',{bubbles:true}));
  }
  h.close();
});

test('F1.9 media unavailable is presented as controlled error and no-media path stays usable',async()=>{
  const h=create({upload(xhr){queueMicrotask(()=>xhr.finish('media_service_unavailable'));}});
  h.fill();
  h.set('media.availability','yes');
  h.select();
  await tick();
  assert(h.root.textContent.includes('servizio immagini'));
  assert(h.root.textContent.includes('Riprova') || h.root.textContent.includes('rimuovi'));
  h.set('media.availability','no');
  assert.equal(h.model.effective('media.availability'),'yes','active media selection prevents silent switch to no');
  h.close();

  const noMedia=create();
  noMedia.fill();
  noMedia.set('media.availability','no');
  noMedia.goReview();
  assert.equal(noMedia.model.step,5);
  assert.equal(noMedia.uploads.length,0);
  noMedia.close();
});

test('F1.9 review and media CSS have stable narrow-screen wrapping with no fixed page-width dependency',()=>{
  const f19=css.slice(css.indexOf('/* F1.9'));
  assert(f19.includes('overflow-wrap:anywhere'));
  assert(f19.includes('min-width:0'));
  assert(f19.includes('.ba-native .ba-native-review-section dl'));
  assert(f19.includes('grid-template-columns:1fr'));
  assert(f19.includes('@media(max-width:768px)'));
  assert(f19.includes('.ba-native{max-width:none}'));
  assert(f19.includes('.ba-native .ba-native-media-actions button'));
});


test('page heading follows every step and back navigation without duplicating the step introduction',()=>{
 const h=create({pageHeader:true});try{
  const title=h.doc.querySelector('[data-native-page-title]'),intro=h.doc.querySelector('[data-native-page-intro]');
  assert.equal(title.textContent,'Cosa è successo?');assert.match(intro.textContent,/Ti guidiamo/);
  h.fill();const steps=Object.keys(h.root.nativeReport.config.steps);
  for(let i=0;i<steps.length;i++){
   const section=h.doc.querySelector('[data-native-step="'+steps[i]+'"]');
   assert.equal(title.textContent,h.root.nativeReport.config.steps[steps[i]]+(i===0?'?':''));
   assert(section.querySelector('h2').hidden);
   // A contextual emergency notice may legitimately follow the hidden heading.
   // Check the original introduction by its copied text, not by sibling position.
   assert([...section.children].some(el=>el.tagName==='P'&&el.hidden&&el.textContent===intro.textContent));
   assert.equal(section.getAttribute('aria-labelledby'),title.id);
   if(i<steps.length-1){h.click('next');assert.equal(h.doc.activeElement,title);}
  }
  h.click('back');assert.equal(title.textContent,h.root.nativeReport.config.steps.contact);assert.equal(h.doc.activeElement,title);
 }finally{h.close();}
});
test('completion and new report reset the page heading without stale review instructions',async()=>{
 const h=create({pageHeader:true});try{
  h.fill();h.goReview();h.submit();await tick();await tick();
  assert.equal(h.model.completed,true);assert.equal(h.doc.querySelector('[data-native-page-title]').textContent,'Segnalazione inviata');
  assert.match(h.doc.querySelector('[data-native-page-intro]').textContent,/attesa di moderazione/);
  h.click('new');assert.equal(h.doc.querySelector('[data-native-page-title]').textContent,'Cosa è successo?');assert.match(h.doc.querySelector('[data-native-page-intro]').textContent,/Ti guidiamo/);
 }finally{h.close();}
});


test('paired place/danger questions preserve progression and show emergency notice outside the row',()=>{
 const h=create();try{
  h.fill();h.model.step=2;h.set('location.immediate_danger','yes');
  const row=h.doc.querySelector('.ba-native-place-danger-row');assert(row);
  assert.deepEqual([...row.querySelectorAll(':scope>[data-native-field]')].map(e=>e.dataset.nativeField),['location.place_type','location.immediate_danger']);
  const notice=h.doc.querySelector('[data-native-emergency-notice]');assert.equal(notice.hidden,false);
  assert.equal(notice.previousElementSibling,row);assert.equal(notice.closest('.ba-native-field-pair'),null);
  const danger=row.querySelector('[data-native-field="location.immediate_danger"] .ba-native-card-grid');assert(danger.classList.contains('ba-native-short-options'));
  h.click('next');assert.equal(h.model.step,3);
 }finally{h.close();}
});
