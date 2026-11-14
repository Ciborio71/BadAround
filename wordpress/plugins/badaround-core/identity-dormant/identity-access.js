/* Unified passwordless access. No profile, code, session or CSRF in browser storage. */
(() => {
  'use strict';
  const api='/wp-json/badaround/v1/identity/';
  let generation=0, currentDialog=null,widgetSequence=0;
  const failure='Operazione non confermata. Controlla il codice o riprova più tardi. La compilazione è conservata.';
  async function request(path,payload={}) {
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),25000);
    try {
      const response=await fetch(api+path,{method:'POST',mode:'same-origin',credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const data=await response.json().catch(()=>({}));
      if(!response.ok||data.ok===false)throw new Error('access_unavailable');
      return data;
    } finally {clearTimeout(timer);}
  }
  function updateSession(authenticated) {
    generation++;
    document.querySelectorAll('[data-ba-account-link]').forEach(a=>{a.textContent=authenticated?'Il mio profilo':'Accedi';a.dataset.baAccountState=authenticated?'authenticated':'anonymous';});
    window.dispatchEvent(new CustomEvent('ba-identity-session',{detail:{authenticated}}));
  }
  function mount(host,{profile=null,onVerified=()=>{},onCancel=null}={}) {
    host.classList.add('ba-identity-access');
    host.innerHTML='<form data-ba-access-form><div data-access-email><label>Email <input name="email" type="email" autocomplete="email" maxlength="191" required></label><label data-access-privacy><input type="checkbox" name="privacy" required> Dichiaro di aver letto l’<a href="/privacy-policy/">informativa privacy</a>.</label></div><p role="status" aria-live="polite" aria-atomic="true" data-access-result></p><div data-access-code hidden><label>Codice a 6 cifre <input name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" disabled></label><p class="ba-access-help" data-access-code-help>Apri l’email e copia qui le 6 cifre. Se non la trovi, controlla anche la cartella spam.</p></div><fieldset data-access-profile hidden><legend>Completa il primo accesso</legend><label>Nome <input name="first_name" autocomplete="given-name" maxlength="191" disabled></label><label>Cognome <input name="last_name" autocomplete="family-name" maxlength="191" disabled></label></fieldset><button type="submit" data-access-submit>Ricevi il codice</button><div class="ba-access-secondary-actions"><button class="ba-access-secondary" type="button" data-access-resend hidden>Invia un nuovo codice</button><button class="ba-access-secondary" type="button" data-access-edit hidden>Correggi l’email</button></div></form>';
    const form=host.querySelector('form'),email=form.elements.email,code=form.elements.code,first=form.elements.first_name,last=form.elements.last_name;
    const result=host.querySelector('[data-access-result]'),submit=host.querySelector('[data-access-submit]'),resend=host.querySelector('[data-access-resend]'),edit=host.querySelector('[data-access-edit]');
    const emailBox=host.querySelector('[data-access-email]'),codeBox=host.querySelector('[data-access-code]'),profileBox=host.querySelector('[data-access-profile]');
    const help=host.querySelector('[data-access-code-help]');help.id='ba-access-code-help-'+(++widgetSequence);code.setAttribute('aria-describedby',help.id);
    const privacy=form.elements.privacy,privacyLink=host.querySelector('[data-access-privacy] a');
    if(document.getElementById('ba-identity-qa-notice'))privacyLink.href='#ba-identity-qa-notice';
    if(profile){email.value=profile.email;email.readOnly=true;privacy.checked=profile.privacy_acknowledged===true;host.querySelector('[data-access-privacy]').hidden=true;}
    let challenge=null,busy=false,lastSent=0,disposed=false,needsProfile=false,resendTimer=null;
    function syncActions(){
      if(disposed)return;
      const remaining=challenge?Math.max(0,Math.ceil((60000-(Date.now()-lastSent))/1000)):0;
      submit.disabled=edit.disabled=busy;resend.disabled=busy||remaining>0;
      if(!busy||!resend.classList.contains('is-loading'))resend.textContent=remaining>0?'Nuovo codice tra '+remaining+' s':'Invia un nuovo codice';
      if(!remaining&&resendTimer){clearInterval(resendTimer);resendTimer=null;}
    }
    function cooldown(){clearInterval(resendTimer);resendTimer=setInterval(syncActions,1000);syncActions();}
    const mask=value=>{const parts=value.split('@');return parts.length===2?parts[0].slice(0,1)+'•••@'+parts[1]:'la tua email';};
    async function start() {
      if(!form.reportValidity())return;
      const data=await request('access/start',{email:email.value.trim(),first_name:profile?.first_name||'',last_name:profile?.last_name||'',privacy_acknowledged:privacy.checked});
      if(data.state!=='code_sent'||!/^[a-f0-9]{32}$/.test(data.challenge_id||''))throw new Error();
      if(disposed)return;
      challenge=data.challenge_id;lastSent=Date.now();needsProfile=false;code.value='';code.disabled=false;code.required=true;code.readOnly=false;
      codeBox.hidden=false;emailBox.hidden=true;privacy.required=false;profileBox.hidden=true;first.disabled=last.disabled=true;
      resend.hidden=false;edit.hidden=profile?.can_edit_email===false;submit.textContent=profile?'Conferma e invia':'Accedi';
      result.textContent='Ti abbiamo inviato un codice a '+mask(email.value)+'. Inseriscilo entro 10 minuti.';
      result.dataset.state='success';cooldown();code.focus();
    }
    async function execute(task,message,button=submit) {
      if(busy||disposed)return;
      const delivery=!challenge||button===resend;
      busy=true;syncActions();button.classList.add('is-loading');button.setAttribute('aria-busy','true');
      button.textContent=message;result.textContent=message;result.dataset.state='busy';
      try{await task();}catch{if(!disposed){result.textContent=delivery?'Invio del codice non confermato. Riprova tra poco. La compilazione è conservata.':failure;result.dataset.state='error';}}
      finally{
        busy=false;
        if(!disposed){
          button.classList.remove('is-loading');button.setAttribute('aria-busy','false');
          submit.textContent=challenge?(needsProfile?'Completa l’accesso':profile?'Conferma e invia':'Accedi'):'Ricevi il codice';
          if(result.dataset.state==='busy')result.dataset.state='idle';syncActions();
        }
      }
    }
    form.addEventListener('submit',event=>{event.preventDefault();if(busy||disposed)return;if(!form.reportValidity()||(challenge&&!/^[0-9]{6}$/.test(code.value)))return;execute(async()=>{
      if(!challenge){await start();return;}
      if(!form.reportValidity()||!/^[0-9]{6}$/.test(code.value))return;
      const data=await request('access/verify',{challenge_id:challenge,code:code.value,first_name:needsProfile?first.value:'',last_name:needsProfile?last.value:''});
      if(disposed)return;
      if(data.state==='profile_required'){
        needsProfile=true;profileBox.hidden=false;first.disabled=last.disabled=false;first.required=last.required=true;code.readOnly=true;
        result.textContent='Email verificata. Per completare il primo accesso inserisci nome e cognome: resteranno privati.';submit.textContent='Completa l’accesso';result.dataset.state='success';first.focus();return;
      }
      if(data.state!=='verified')throw new Error();
      code.value='';challenge=null;updateSession(true);result.textContent=profile?'Email confermata. Preparazione dell’invio…':'Email confermata. Accesso in corso…';await onVerified();if(!disposed){result.dataset.state='success';result.textContent='Accesso completato.';}
    },challenge?'Verifica del codice…':'Invio del codice…');});
    resend.addEventListener('click',()=>execute(async()=>{
      if(Date.now()-lastSent<60000){result.textContent='Attendi un minuto prima di richiedere un nuovo codice.';return;}
      // Validate only the email/acknowledgement; an old code is not needed to resend.
      code.required=false;code.disabled=true;first.required=last.required=false;
      try{await start();}finally{if(challenge){code.disabled=false;code.required=true;}}
    },'Invio del nuovo codice…',resend));
    edit.addEventListener('click',()=>{
      if(busy)return;
      if(profile){onCancel?.({editEmail:true});return;}
      challenge=null;code.value='';code.disabled=true;code.required=false;emailBox.hidden=false;codeBox.hidden=profileBox.hidden=true;first.disabled=last.disabled=true;
      privacy.required=true;resend.hidden=edit.hidden=true;submit.textContent='Ricevi il codice';result.dataset.state='idle';syncActions();result.textContent='Correggi l’email e richiedi un nuovo codice.';email.focus();
    });
    return {start:()=>execute(start,'Invio del codice…'),dispose:()=>{disposed=true;clearInterval(resendTimer);challenge=null;code.value='';first.value=last.value='';}};
  }
  async function ensureIdentity(reporter,{onPhase=()=>{}}={}) {
    const phase=value=>{try{onPhase(value);}catch(_){}};
    if(!reporter||typeof reporter.email!=='string'||reporter.privacy_acknowledged!==true)return false;
    phase('checking');
    try{const data=await request('access/check',{email:reporter.email});if(data.matches===true){updateSession(true);phase('verified');return true;}}catch{phase('failed');return false;}
    if(currentDialog)return false;
    phase('confirming');
    return new Promise(resolve=>{
      const dialog=document.createElement('dialog');dialog.className='ba-identity-access-dialog ba-identity-access';dialog.setAttribute('aria-labelledby','ba-access-dialog-title');
      const title=document.createElement('h2');title.id='ba-access-dialog-title';title.textContent='Conferma la tua email';
      const intro=document.createElement('p');intro.className='ba-access-intro';intro.textContent='Manca un ultimo passaggio: conferma l’email per inviare la segnalazione. La compilazione è conservata.';
      const host=document.createElement('div'),cancel=document.createElement('button');cancel.className='ba-access-secondary ba-access-cancel';cancel.type='button';cancel.textContent='Torna alla segnalazione';
      dialog.append(title,intro,host,cancel);document.body.append(dialog);currentDialog=dialog;let finished=false;
      const finish=value=>{if(finished)return;finished=true;phase(value===true?'verified':value?.editEmail?'editing':'cancelled');widget.dispose();dialog.close?.();dialog.remove();currentDialog=null;resolve(value);};
      const widget=mount(host,{profile:reporter,onVerified:async()=>{
        const check=await request('access/check',{email:reporter.email});if(check.matches!==true)throw new Error();finish(true);
      },onCancel:finish});
      cancel.addEventListener('click',()=>finish(false));dialog.addEventListener('cancel',event=>{event.preventDefault();finish(false);});
      dialog.showModal();widget.start();
    });
  }
  window.BadAroundAccess={request,mount,ensureIdentity,updateSession};
  const initial=generation;
  request('session').then(data=>{if(generation===initial)updateSession(data.authenticated===true);}).catch(()=>{});
})();
