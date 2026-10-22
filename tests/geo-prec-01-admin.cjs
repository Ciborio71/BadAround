'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{JSDOM}=require('jsdom');
for(const [arg,label] of [['--html','Punto esatto'],['--html-approx','Area approssimativa']])test('new moderation SSR is read-only '+label,()=>{
 const html=execFileSync(process.env.BA_PHP||'php',['tests/geo-public-04-moderation.php',arg,'--user-authority'],{encoding:'utf8'}),d=new JSDOM(html).window.document;
 assert(d.body.textContent.includes('Posizione pubblica richiesta:'));assert(d.body.textContent.includes(label));
 assert.equal(d.querySelector('#ba-geo-mode, #ba-geo-reason, #ba-geo-exact-confirm, #ba-geo-decision-form'),null);
 assert.equal(d.querySelector('form form'),null);assert(d.querySelector('#ba-reject-event-form'));
 assert.equal(d.querySelector('[name="public_title"]').disabled,false);
});
