(function(){
'use strict';
document.addEventListener('click',function(e){
 const btn=e.target.closest('[data-ba-contribution-open]');
 if(!btn)return;
 const panel=btn.closest('.ba-contribution-box')?.querySelector('[data-ba-contribution-panel]');
 if(!panel)return;
 const opening=panel.hidden;panel.hidden=!opening;
 btn.setAttribute('aria-expanded',String(opening));
 const label=btn.querySelector('[data-ba-contribution-label]')||btn;label.textContent=opening?'Chiudi il modulo riservato':'Contatta l’autore della segnalazione';
 if(opening){panel.scrollIntoView({behavior:window.matchMedia?.('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});panel.querySelector('select')?.focus({preventScroll:true});}
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
