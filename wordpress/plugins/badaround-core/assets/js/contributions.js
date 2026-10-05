(function(){
document.addEventListener('click',function(e){
 const btn=e.target.closest('[data-ba-contribution-open]');
 if(!btn)return;
 const panel=document.querySelector('[data-ba-contribution-panel]');
 if(!panel)return;
 panel.hidden=false;
 btn.setAttribute('aria-expanded','true');
 panel.scrollIntoView({behavior:'smooth',block:'start'});
});
document.addEventListener('change',function(e){
 if(!e.target.matches('[name="public_identity_mode"]'))return;
 const alias=document.querySelector('[data-ba-contribution-alias]');
 if(alias) alias.hidden=e.target.value!=='public_alias';
});
document.addEventListener('submit',async function(e){
 const form=e.target.closest('[data-ba-contribution-form]');
 if(!form)return;
 e.preventDefault();
 const status=form.querySelector('[data-ba-contribution-status]');
 status.textContent='Invio in corso…';
 const data=new FormData(form);
 try{
  const res=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin'});
  const json=await res.json();
  if(!res.ok) throw new Error((json&&json.message)||'Errore durante l’invio.');
  form.reset();
  status.textContent=(json&&json.message)||'Controlla la tua email per confermare il contributo.';
 }catch(err){status.textContent=err.message||'Errore durante l’invio.';}
});
})();