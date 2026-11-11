(function(){
'use strict';
function choosePanel(kind,forceOpen=false){
 const isPublic=kind==='public',selected=document.querySelector(isPublic?'[data-ba-comment-panel]':'[data-ba-contribution-panel]');
 if(!selected)return;
 const opening=forceOpen||selected.hidden;
 for(const type of ['public','author']){
  const panel=document.querySelector(type==='public'?'[data-ba-comment-panel]':'[data-ba-contribution-panel]');
  const button=document.querySelector(type==='public'?'[data-ba-comment-open]':'[data-ba-contribution-open]');
  const active=opening&&type===kind;
  if(panel)panel.hidden=!active;
  if(button){button.setAttribute('aria-expanded',String(active));button.classList.toggle('is-active',active);}
 }
 if(opening){selected.scrollIntoView({behavior:window.matchMedia?.('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});selected.querySelector(isPublic?'textarea':'select')?.focus({preventScroll:true});}
}
document.addEventListener('ba-open-public-comment',()=>choosePanel('public',true));
document.addEventListener('click',function(e){
 const button=e.target.closest('[data-ba-comment-open],[data-ba-contribution-open]');
 if(!button)return;
 choosePanel(button.matches('[data-ba-comment-open]')?'public':'author');
});
document.addEventListener('change',function(e){
 if(e.target.matches('[name="visibility_requested"]')){
  const submit=e.target.closest('form')?.querySelector('[type="submit"]');
  if(submit)submit.textContent=e.target.value==='reserved'?'Invia informazioni riservate':'Invia il commento';
  return;
 }
 if(!e.target.matches('[name="public_identity_mode"]'))return;
 const alias=e.target.closest('form')?.querySelector('[data-ba-contribution-alias]');
 if(alias)alias.hidden=e.target.value!=='public_alias';
});
document.addEventListener('submit',async function(e){
 const form=e.target.closest('[data-ba-contribution-form]');
 if(!form)return;
 e.preventDefault();if(form.dataset.submitting==='true')return;
 const status=form.querySelector('[data-ba-contribution-status]'),button=form.querySelector('[type="submit"]');
 const data=new FormData(form);form.dataset.submitting='true';form.setAttribute('aria-busy','true');
 if(button)button.disabled=true;
 status.dataset.state='pending';status.textContent='Invio in corso…';
 try{
  const res=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin'});
  const json=await res.json();
  if(!res.ok)throw new Error((json&&json.message)||'Errore durante l’invio.');
  form.reset();if(button)button.textContent=form.querySelector('[name=visibility_requested]')?.value==='reserved'?'Invia informazioni riservate':'Invia il commento';const alias=form.querySelector('[data-ba-contribution-alias]');if(alias)alias.hidden=true;
  status.dataset.state='success';status.textContent=(json&&json.message)||'Controlla la tua email per confermare il contributo.';
 }catch(err){status.dataset.state='error';status.textContent=err.message||'Errore durante l’invio. I dati inseriti restano nel modulo.';}
 finally{delete form.dataset.submitting;form.removeAttribute('aria-busy');if(button)button.disabled=false;}
});
})();
