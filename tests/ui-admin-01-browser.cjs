const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const root=path.join(__dirname,'..'), fixture=require('./fixtures/ui-admin-01/staging-markup.json');
const cssPath='wordpress/plugins/badaround-core/assets/css/classic-editor-publish-box.css', css=fs.readFileSync(path.join(root,cssPath),'utf8');
const out=path.join(root,'evidence/ui-admin-01');fs.mkdirSync(out,{recursive:true});
fs.copyFileSync(path.join(__dirname,'fixtures/ui-admin-01/before.jpg'),path.join(out,'before-staging.jpg'));
const changed=execFileSync('git',['diff','--name-only','eacfdc2e77cce877c8b1b6961c28abf2b8f61d1d','HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n');
assert(changed.filter(p=>p.startsWith('wordpress/')).every(p=>[cssPath,'wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php'].includes(p)),'Unexpected runtime change');
assert(!/https?:|_wpnonce/.test(fixture.boxes),'Fixture must contain no active URL or nonce');
const html=(fixed=true,type='ba_evento')=>`<!doctype html><html><head><meta charset="utf-8"><style>${fixture.css}</style>${fixed?`<style id="candidate">${css}</style>`:''}</head><body class="${fixture.bodyClass.replace('post-type-ba_evento','post-type-'+type)}"><div id="wpwrap"><div id="wpcontent"><div id="wpbody-content"><div id="poststuff"><form id="post"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content"><h1>Classic Editor — offline staging markup</h1></div><div id="postbox-container-1" class="postbox-container"><div id="side-sortables">${fixture.boxes}</div></div></div></form><form id="ba-reject-event-form"></form></div></div></div></div></body></html>`;
(async()=>{const browser=await chromium.launch({headless:true});const results=[];try{
for(const width of [1440,1280,1024,768]){
 const page=await browser.newPage({viewport:{width,height:1100}});await page.route('**/*',r=>r.abort());await page.setContent(html());
 const rules=await page.evaluate(()=>[...document.getElementById('candidate').sheet.cssRules].map(r=>r.selectorText));
 assert(rules.length>0&&rules.every(s=>s&&s.split(',').every(v=>v.trim().startsWith('body.post-type-ba_evento:not(.block-editor-page) #'))),'Unscoped CSS rule');
 const geometry=await page.evaluate(()=>{const box=document.getElementById('submitdiv').getBoundingClientRect();const ids=['save-post','post-preview','publish'];return {box:box.toJSON(),controls:ids.map(id=>({id,...document.getElementById(id).getBoundingClientRect().toJSON()})),overflow:document.documentElement.scrollWidth>innerWidth,labels:ids.map(id=>{const e=document.getElementById(id);return e.value||e.innerText})};});
 assert(!geometry.overflow,JSON.stringify(geometry));
 const [save,preview]=geometry.controls;assert(Math.abs(save.y-preview.y)<1,'Top buttons baseline mismatch');assert(Math.abs(save.height-preview.height)<1,'Top buttons height mismatch');assert(save.right+7<=preview.left,'Buttons overlap / spacing missing');
 for(const r of geometry.controls)assert(r.left>=geometry.box.left&&r.right<=geometry.box.right,'Clipped button');
 const visible=await page.locator('#submitdiv .misc-pub-section').all();for(const row of visible)assert(await row.isVisible());
 const baselineLabels=await page.locator('#submitdiv .button, #ba_moderation_actions .inside .button').evaluateAll(es=>es.map(e=>({id:e.id,text:e.value||e.textContent,name:e.name,form:e.getAttribute('form'),tabindex:e.getAttribute('tabindex')})));
 await page.evaluate(()=>{window.submissions=[];for(const f of document.forms)f.addEventListener('submit',e=>{e.preventDefault();window.submissions.push({form:f.id,submitter:e.submitter.id||e.submitter.textContent,reason:new FormData(f).get('reason')});});});
 assert.equal(await page.locator('#ba-rejection-reason').evaluate(e=>e.form.id),'ba-reject-event-form');
 await page.locator('#save-post').click();assert.deepEqual(await page.evaluate(()=>window.submissions),[{form:'post',submitter:'save-post',reason:null}]);
 const reject=page.getByRole('button',{name:'Rifiuta / scarta',exact:true});await reject.click();assert.equal(await page.evaluate(()=>window.submissions.length),1);assert.equal(await page.locator('#ba-rejection-reason').evaluate(e=>e.validity.valueMissing),true);
 await page.locator('#ba-rejection-reason').fill('QA offline isolated rejection');await reject.click();assert.equal(await page.evaluate(()=>window.submissions.at(-1).form),'ba-reject-event-form');
 await page.locator('#publish').click();assert.equal(await page.evaluate(()=>window.submissions.at(-1).submitter),'publish');
 assert.equal(await page.locator('#post-preview').getAttribute('target'),'wp-preview-343');assert.equal(await page.locator('#post-preview').getAttribute('href'),'#');
 assert.equal(await page.getByRole('link',{name:'Approva e pubblica',exact:true}).getAttribute('href'),'#');
 await page.locator('#save-post').press('Tab');assert.equal(await page.evaluate(()=>document.activeElement.id),'post-preview');
 const focus=await page.locator('#post-preview').evaluate(e=>({shadow:getComputedStyle(e).boxShadow,outline:getComputedStyle(e).outlineStyle}));assert(focus.shadow!=='none'||!['none','hidden'].includes(focus.outline));
 if(width===1440){await page.locator('#submitdiv').screenshot({path:path.join(out,'after-desktop.png')});await page.locator('#post-preview').press('Shift+Tab');await page.locator('#save-post').press('Tab');await page.locator('#submitdiv').screenshot({path:path.join(out,'focus.png')});await page.locator('#ba_moderation_actions').screenshot({path:path.join(out,'rejection-area.png')});}
 if(width===768)await page.locator('#submitdiv').screenshot({path:path.join(out,'after-narrow.png')});
 await page.locator('#ba-rejection-reason').fill('');
 await page.addStyleTag({content:'#postbox-container-1{width:220px!important}'});
 const narrow=await page.locator('#submitdiv').evaluate(e=>({width:e.clientWidth,scroll:e.scrollWidth,buttons:[...e.querySelectorAll('#save-post,#post-preview,#publish')].map(b=>({right:b.getBoundingClientRect().right,boxRight:e.getBoundingClientRect().right}))}));assert(narrow.scroll<=narrow.width);assert(narrow.buttons.every(b=>b.right<=b.boxRight));
 await page.addStyleTag({content:'#postbox-container-1{width:280px!important}'});
 await page.locator('#save-action .spinner').evaluate(e=>e.classList.add('is-active'));
 assert(await page.locator('#save-action .spinner').isVisible());
 results.push({width,geometry,focus,labels:baselineLabels,isolatedSave:'PASS',isolatedReject:'PASS',isolatedPublishSubmit:'PASS',previewAttributes:'UNCHANGED',approvalAttributes:'UNCHANGED'});await page.close();
}
// Negative controls: candidate CSS has no visual effect on post/page/block screens.
for(const type of ['post','page']){const page=await browser.newPage({viewport:{width:1440,height:1100}});await page.route('**/*',r=>r.abort());await page.setContent(html(false,type));const before=await page.locator('#submitdiv').evaluate(e=>[...e.querySelectorAll('*')].map(x=>{const s=getComputedStyle(x);return [s.display,s.padding,s.margin,s.color,s.lineHeight,s.float]}));await page.addStyleTag({content:css});const after=await page.locator('#submitdiv').evaluate(e=>[...e.querySelectorAll('*')].map(x=>{const s=getComputedStyle(x);return [s.display,s.padding,s.margin,s.color,s.lineHeight,s.float]}));assert.deepEqual(after,before);await page.close();}
fs.writeFileSync(path.join(out,'results.json'),JSON.stringify({baseline:'eacfdc2e77cce877c8b1b6961c28abf2b8f61d1d',mode:'Offline sanitized staging DOM; no live writes, no candidate deploy',results},null,2));console.log('PASS: four viewport layouts; 220px wrap; keyboard/focus; independent form validation/submission; non-target CSS no-op; runtime allowlist.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
