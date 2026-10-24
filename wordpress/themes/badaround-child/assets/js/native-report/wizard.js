(function () {
  'use strict';
  const ns = window.BadAroundNative;
  function boot(container) {
    if (container.nativeReport) return; // Reinitialization must retain the current logical wizard.
    let disposed=false; const listeners=[]; let submitWaitTimer=null;
    function listen(target,type,handler) { target.addEventListener(type,handler);listeners.push(()=>target.removeEventListener(type,handler)); }
    function teardown() { if(disposed)return; disposed=true;clearTimeout(submitWaitTimer);submissionFeedback.hidden=true;listeners.forEach(remove=>remove());mediaView?.dispose();smartAddress?.dispose(); }
    const config = JSON.parse(container.querySelector('[data-native-config]').textContent);
    const model = new ns.Model(config);
    // These are explicit reporter choices in the UI; canonical fallback defaults stay frozen.
    function clearReporterChoices(){for(const path of ['reward.status','reporter.public_identity_mode'])model.set(path,undefined);}
    clearReporterChoices();
    const form = container.querySelector('[data-native-form]');
    const publicLocationChoice = form.querySelector('[data-native-public-location-choice]');
    const radiusField=form.querySelector('[data-native-radius-field]'),radiusSelect=radiusField?.querySelector('select');
    const steps = Object.keys(config.steps);
    const wrappers = Array.from(form.querySelectorAll('[data-native-field]'));
    // One contextual, non-blocking notice. No model value or payload field is added.
    const emergencyNotice=document.createElement('div');
    emergencyNotice.className='ba-native-emergency-notice';
    emergencyNotice.dataset.nativeEmergencyNotice='';
    emergencyNotice.hidden=true;
    emergencyNotice.setAttribute('role','status');
    emergencyNotice.setAttribute('aria-live','polite');
    emergencyNotice.setAttribute('aria-atomic','true');
    const emergencyCopy='Se c’è un pericolo immediato o un’emergenza, contatta subito il 112. BadAround non sostituisce i servizi di emergenza.';
    function syncEmergencyNotice(current){
      const rules=config.emergencyNotice;
      const triggered=!!rules && (rules.subtypes.includes(model.effective('event.subtype')) ||
        (model.active('location.immediate_danger') && rules.dangerValues.includes(model.effective('location.immediate_danger'))));
      const anchor=current===config.fields['event.subtype'].step
        ? wrappers.find(w=>w.dataset.nativeField==='event.subtype')
        : current===config.fields['location.immediate_danger'].step
          ? wrappers.find(w=>w.dataset.nativeField==='location.immediate_danger')
          : form.querySelector(`[data-native-step="${current}"] h2`);
      if(anchor && anchor.nextElementSibling!==emergencyNotice)anchor.after(emergencyNotice);
      emergencyNotice.hidden=!triggered;
      const text=triggered?emergencyCopy:'';
      if(emergencyNotice.textContent!==text)emergencyNotice.textContent=text;
    }
    const conditionalStatus=document.createElement('p');conditionalStatus.className='ba-native-conditional-status';
    conditionalStatus.setAttribute('role','status');conditionalStatus.setAttribute('aria-live','polite');conditionalStatus.setAttribute('aria-atomic','true');form.before(conditionalStatus);
    let activeSpecifications=new Set();
    const next = form.querySelector('[data-native-next]'), back = form.querySelector('[data-native-back]'), submit = form.querySelector('[data-native-submit]');
    const success = container.querySelector('[data-native-success]');
    // Outside the busy form so assistive technology can announce progress.
    const submissionFeedback=document.createElement('div');
    submissionFeedback.className='ba-native-submit-feedback';submissionFeedback.hidden=true;
    submissionFeedback.dataset.nativeSubmitFeedback='';
    submissionFeedback.setAttribute('role','status');submissionFeedback.setAttribute('aria-live','polite');submissionFeedback.setAttribute('aria-atomic','true');
    form.after(submissionFeedback);
    function syncSubmissionFeedback(){
      submit.classList.toggle('is-loading',model.busy);
      if(model.busy){
        if(submissionFeedback.hidden){
          submissionFeedback.textContent='Invio in corso… Attendi la conferma prima di chiudere la pagina.';
          submissionFeedback.hidden=false;
          submissionFeedback.scrollIntoView?.({block:'nearest',behavior:'instant'});
          submitWaitTimer=setTimeout(()=>{
            if(!disposed&&model.busy)submissionFeedback.textContent='Stiamo elaborando la segnalazione. Attendi la conferma: non occorre premere di nuovo Invia.';
          },6000);
        }
      }else{
        clearTimeout(submitWaitTimer);submitWaitTimer=null;submissionFeedback.hidden=true;
      }
    }

    let mediaView = null;
    const smartAddress = ns.SmartAddress?.mount(container, config, model);
    function mountMedia() {
      if (ns.mountMedia && config.media) mediaView = ns.mountMedia(container, model, config.media, () => render());
    }
    // Presentation classification of frozen F1.3 errors, never a validator.
    const correctableCodes = new Set(['required_field','invalid_type', 'missing_required_field', 'conditional_field_required', 'invalid_email', 'invalid_phone', 'invalid_date_time', 'invalid_enum', 'invalid_category_subtype', 'invalid_location', 'invalid_plate', 'invalid_boolean', 'invalid_array', 'invalid_number', 'value_too_short', 'value_too_long', 'value_too_small', 'value_too_large', 'reward_amount_required', 'reward_confirmation_required']);
    const read = wrapper => {
      const field = config.fields[wrapper.dataset.nativeField];
      const inputs = wrapper.querySelectorAll('[data-native-input]');
      return field.type === 'array' ? Array.from(inputs).filter(i => i.checked).map(i => i.value) : field.type === 'bool' ? inputs[0].checked : inputs[0].value;
    };
    function review() {
      const region = form.querySelector('[data-native-review]'); region.replaceChildren();
      const sections = new Map();
      Object.entries(config.fields).forEach(([path, field]) => {
        if (!model.active(path) || (declarationPaths.includes(path) && path!=='consents.truthfulness')) return;
        // The review is user-facing: never print canonical territory metadata or coordinates.
        if (path.startsWith('location.') && path !== 'location.exact_address' && !field.sourceField) return;
        const value = model.effective(path); if (value === undefined || value === '') return;
        const stepKey = field.step || 'review';
        let section = sections.get(stepKey);
        if (!section) {
          section = document.createElement('section');
          section.className = 'ba-native-review-section';
          const head = document.createElement('div');
          head.className = 'ba-native-review-section__head';
          const heading = document.createElement('h3');
          heading.textContent = config.steps[stepKey] || 'Riepilogo';
          const edit = document.createElement('button');
          edit.type = 'button';
          edit.className = 'ba-native-review-edit';
          edit.textContent = 'Modifica';
          edit.setAttribute('aria-label','Modifica: ' + (config.steps[stepKey] || 'sezione'));
          edit.addEventListener('click',()=>{
            const index=steps.indexOf(stepKey);
            if(index<0 || model.busy || model.snapshot || model.completed)return;
            model.step=index;ns.presentErrors(container,[],config);render(true);
          });
          head.append(heading,edit);
          const list = document.createElement('dl');
          section.append(head, list);
          sections.set(stepKey, section);
          region.append(section);
        }
        const list = section.querySelector('dl');
        const term = document.createElement('dt'); term.textContent = path==='consents.truthfulness'?'Dichiarazioni, regole e presa visione della privacy':field.label;
        const detail = document.createElement('dd');
        const label = v => field.options[v] || String(v);
        const valueText = typeof value === 'boolean'
          ? (value ? 'Confermato' : 'Non confermato')
          : Array.isArray(value) ? value.map(label).join(', ') : label(value);
        const valueNode = document.createElement('span'); valueNode.textContent = valueText;
        detail.append(valueNode);
        term.dataset.nativeReviewField=path;
        if (field.help && !['Dato riservato: non viene pubblicato integralmente.','La pubblicazione avviene solo dopo moderazione.'].includes(field.help)) {
          const help = document.createElement('small');
          help.className = 'ba-native-review-help';
          help.textContent = field.help;
          detail.append(help);
        }
        list.append(term, detail);
      });
      if (publicLocationChoice && config.publicLocationRequest) {
        let locationSection = sections.get('location');
        if (!locationSection) {
          locationSection = document.createElement('section');locationSection.className='ba-native-review-section';
          const head=document.createElement('div');head.className='ba-native-review-section__head';
          const heading=document.createElement('h3');heading.textContent=config.steps.location || 'Dove e quando';
          const edit=document.createElement('button');edit.type='button';edit.className='ba-native-review-edit';edit.textContent='Modifica';edit.setAttribute('aria-label','Modifica: '+(config.steps.location || 'Dove e quando'));
          edit.addEventListener('click',()=>{if(model.busy||model.snapshot||model.completed)return;model.step=steps.indexOf('location');ns.presentErrors(container,[],config);render(true);});
          head.append(heading,edit);locationSection.append(head,document.createElement('dl'));sections.set('location',locationSection);region.append(locationSection);
        }
        const term=document.createElement('dt');term.textContent='Posizione pubblica richiesta';
        const detail=document.createElement('dd');detail.textContent=model.publicLocationLabel();
        locationSection.querySelector('dl').append(term,detail);
      }
      // Follow the seven-step sequence even when schema fields are ordered differently.
      steps.forEach(key => { if (sections.has(key)) region.append(sections.get(key)); });
      if (mediaView && mediaView.media.count) {
        const mediaSection = document.createElement('section');
        mediaSection.className = 'ba-native-review-section';
        const heading = document.createElement('h3'); heading.textContent = config.steps.media || 'Immagini';
        const title=document.createElement('p');title.textContent=`Immagini accettate: ${new Set(mediaView.media.items.filter(row=>row.state==='accepted').map(row=>row.descriptor.media_id)).size}. Gli originali restano riservati.`;
        const previews=document.createElement('ul');previews.className='ba-native-review-media';
        mediaView.media.items.filter(row=>row.state==='accepted').forEach(row=>{
          const li=document.createElement('li'), img=document.createElement('img'), state=document.createElement('span');
          img.src=row.preview;img.alt='Anteprima locale';state.textContent='Accettata';li.append(img,state);previews.append(li);
        });
        mediaSection.append(heading,title,previews);
        const unresolved=mediaView.media.items.filter(row=>row.state!=='accepted' || row.error).length;
        if(unresolved){const pending=document.createElement('p');pending.className='ba-native-error';pending.textContent=`${unresolved} immagini da completare o rimuovere nella sezione Immagini prima dell’invio.`;mediaSection.append(pending);}
        region.append(mediaSection);
      }
    }
    // Native presentation only: selects remain the sole canonical model inputs.
    const categoryCards = {
      vehicle: {title:'Veicoli', desc:'Auto, moto e altri mezzi: furti, danneggiamenti e situazioni da segnalare.', icon:'vehicle', color:'#1677E8'},
      property: {title:'Case e attività', desc:'Furti, tentati furti e altri eventi che riguardano abitazioni, negozi e uffici.', icon:'property', color:'#C9153D'},
      hazard: {title:'Pericoli', desc:'Ostacoli, rischi stradali o ambientali e situazioni che richiedono attenzione.', icon:'hazard', color:'#F57C00'},
      public_space: {title:'Spazi pubblici', desc:'Vandalismi, degrado e altre situazioni che interessano aree comuni.', icon:'public', color:'#0AA184'},
      animal: {title:'Animali', desc:'Animali smarriti, avvistati o in difficoltà.', icon:'animal', color:'#7040C8'},
      item_document: {title:'Oggetti e documenti', desc:'Chiavi, documenti, dispositivi e altri oggetti smarriti o ritrovati.', icon:'object', color:'#E3A415'}
    };
    // Same SVG geometry and stroke/fill treatment as the home category pins.
    const cardIcons = {
      vehicle:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 14h14l-1.5-5h-11L5 14Zm1 0v3m12-3v3M8 17h8M8 9l1-3h6l1 3"/></svg>',
      property:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11 12 4l8 7v9h-6v-6h-4v6H4v-9Z"/></svg>',
      hazard:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 3.5 20h17L12 4Z"/><path d="M12 9v5m0 3h.01"/></svg>',
      public:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16M6 18V9h12v9M8 9V6h8v3M9 13h2m2 0h2"/></svg>',
      animal:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor" stroke="none"><path d="M8 11c-1.3 0-2.3-1.3-2.3-2.8S6.5 5.5 7.8 5.5s2.2 1.2 2.2 2.7S9.3 11 8 11Zm8 0c-1.3 0-2-1.3-2-2.8s.9-2.7 2.2-2.7 2.1 1.2 2.1 2.7S17.3 11 16 11Zm-4 9c-3.2 0-5.4-1.7-5.4-3.8 0-1.8 1.4-3 3-3.9.8-.5 1.2-1.5 2.4-1.5s1.6 1 2.4 1.5c1.6.9 3 2.1 3 3.9 0 2.1-2.2 3.8-5.4 3.8Z"/></svg>',
      object:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14v12H5V7Zm3-3h8v3H8V4Zm1 7h6m-6 4h4"/></svg>'
    };
    const cardGroups = new Map();
    // Presentation adapters preserve every canonical value and validation rule.
    // One explicit action for required declarations only; optional contact preferences stay independent.
    const declarationPaths=['consents.truthfulness','consents.media_rights','consents.publication_rules','consents.terms','consents.privacy'];
    const declarationFields=declarationPaths.map(path=>wrappers.find(w=>w.dataset.nativeField===path));
    const finalBox=document.createElement('fieldset');finalBox.className='ba-native-final-statements';
    const finalLegend=document.createElement('legend');finalLegend.textContent='Prima di inviare';finalBox.append(finalLegend);
    declarationFields[0].before(finalBox);
    const declaration=document.createElement('div');declaration.className='ba-native-field';declaration.dataset.betaDeclarations='';
    const declarationLabel=document.createElement('label');declarationLabel.className='ba-native-choice';declarationLabel.htmlFor='ba-native-declarations';
    const declarationInput=document.createElement('input');declarationInput.type='checkbox';declarationInput.id='ba-native-declarations';declarationInput.dataset.betaConfirm='';
    const declarationCopy=document.createElement('span');
    const declarationTitle=document.createElement('strong');declarationTitle.textContent='Confermo di aver fornito informazioni in buona fede.';declarationCopy.append(declarationTitle);
    function policyLink(label,path){
      const link=document.createElement('a');link.textContent=label;link.href=path;
      link.target='_blank';link.rel='noopener noreferrer';link.style.textDecoration='underline';
      link.setAttribute('aria-label',label+' (si apre in una nuova scheda)');return link;
    }
    const declarationDetail=document.createElement('p');declarationDetail.id='ba-native-declarations-detail';declarationDetail.className='ba-native-declaration-detail';
    declarationDetail.append('Dichiaro di avere il diritto di condividere eventuali contenuti allegati, di aver letto e accettato le ',policyLink('regole di pubblicazione','/regole-di-pubblicazione/'),' e i ',policyLink('termini e le condizioni','/termini-e-condizioni/'),' di BadAround e di aver preso visione dell’',policyLink('informativa privacy','/privacy-policy/'),'.');
    const declarationRequired=document.createElement('span');declarationRequired.dataset.nativeRequired='';declarationRequired.textContent=' (obbligatorio)';declarationCopy.append(declarationRequired);
    declarationLabel.append(declarationInput,declarationCopy);declaration.append(declarationLabel,declarationDetail);
    const declarationError=document.createElement('p');declarationError.id='ba-native-declarations-error';declarationError.className='ba-native-error';declarationError.hidden=true;
    declarationInput.setAttribute('aria-describedby','ba-native-declarations-detail ba-native-declarations-help '+declarationError.id);declaration.append(declarationError);finalBox.append(declaration);
    declarationFields.forEach(w=>{w.dataset.betaBundled='';finalBox.append(w);});
    const finalHelp=document.createElement('p');finalHelp.id='ba-native-declarations-help';finalHelp.className='ba-native-help';finalHelp.textContent='La segnalazione sarà sottoposta a moderazione prima della pubblicazione. BadAround può modificare, limitare o non pubblicare contenuti che non rispettano le regole o che potrebbero esporre dati riservati.';finalBox.append(finalHelp);
    const cookieHelp=document.createElement('p');cookieHelp.className='ba-native-help';cookieHelp.append('Consulta anche la ',policyLink('Cookie Policy','/cookie-policy/'),'.');finalBox.append(cookieHelp);
    listen(declarationInput,'change',()=>{
      if(model.busy||model.snapshot||model.completed)return;
      declarationPaths.forEach(path=>{model.set(path,declarationInput.checked);wrappers.find(w=>w.dataset.nativeField===path).querySelector('input').checked=declarationInput.checked;});
      declarationError.hidden=true;declarationInput.removeAttribute('aria-invalid');ns.presentErrors(container,[],config);render();
    });
    const authorityWrapper=wrappers.find(w=>w.dataset.nativeField==='authority.status');
    const authorityCanonical=authorityWrapper.querySelector('[data-native-input]');authorityCanonical.hidden=true;
    const authorityChoice=document.createElement('select');authorityChoice.id='ba-native-authority-choice';authorityChoice.dataset.betaAuthority='';
    authorityChoice.setAttribute('aria-describedby',authorityCanonical.getAttribute('aria-describedby'));
    const authorityOptions={'':'Seleziona…','yes:public_service':'Numero Unico di Emergenza 112','yes:police':'Polizia di Stato','yes:carabinieri':'Carabinieri','yes:local_police':'Polizia Locale','yes:fire_service':'Vigili del Fuoco','yes:other':'Altra autorità o servizio','no':'No','not_yet':'Non ancora','not_applicable':'Non applicabile','prefer_not':'Preferisco non indicarlo','yes:':'Sì, non so quale servizio'};
    Object.entries(authorityOptions).forEach(([value,label])=>authorityChoice.add(new Option(label,value)));
    // Preserve already supported service values when restoring an in-memory answer.
    for(const [value,label] of Object.entries(config.fields['authority.type'].options))if(!authorityOptions['yes:'+value])authorityChoice.add(new Option(label,'yes:'+value));
    authorityCanonical.after(authorityChoice);const authorityLabel=authorityWrapper.querySelector('label');const canonicalAuthorityLabel=authorityLabel.cloneNode(true);canonicalAuthorityLabel.hidden=true;authorityCanonical.before(canonicalAuthorityLabel);authorityLabel.htmlFor=authorityChoice.id;
    listen(authorityChoice,'input',event=>event.stopPropagation());
    listen(authorityChoice,'change',event=>{
      event.stopPropagation();
      if(model.busy||model.snapshot||model.completed)return;
      const [status,type]=authorityChoice.value.split(':');model.set('authority.status',status);authorityCanonical.value=status;
      if(status==='yes'){model.set('authority.type',type||undefined);wrappers.find(w=>w.dataset.nativeField==='authority.type').querySelector('select').value=type||'';}
      authorityChoice.removeAttribute('aria-invalid');render();nextQuestion(authorityWrapper);
    });
    function unanswered(path){
      const value=model.effective(path);return value===undefined||value===null||value===''||value===false||(Array.isArray(value)&&!value.length);
    }
    function questionTarget(wrapper){
      if(wrapper===authorityWrapper)return authorityChoice;
      if(wrapper===declaration)return declarationInput;
      return wrapper.querySelector('.ba-native-card-grid input:checked:not(:disabled)')||wrapper.querySelector('.ba-native-card-grid input:not(:disabled)')||wrapper.querySelector('[data-native-input]:not([disabled]):not([hidden])');
    }
    function syncFlow(){
      const locked=model.busy||!!model.snapshot||model.completed;
      declarationInput.checked=declarationPaths.every(path=>model.effective(path)===true);
      const status=model.effective('authority.status')||'',type=model.effective('authority.type')||'';
      authorityChoice.value=status==='yes'?'yes:'+type:status;
      let number=0;
      for(const section of form.querySelectorAll('[data-native-step]')){
        let blocked=false,pairBlocked=false;
        let units=[...section.querySelectorAll('[data-native-field],[data-beta-declarations]')];
        if(section.dataset.nativeStep==='location'){
          numberLabel(section.querySelector('[data-native-smart-address]>label'),++number);
          units=units.filter(unit=>unit.dataset.nativeField?.startsWith('time.'));
          blocked=!model.effective('location.exact_address');
          section.querySelector('[data-native-time-fields]').hidden=blocked;
        }
        for(const unit of units){
          const path=unit.dataset.nativeField,field=config.fields[path],bundled=unit.hasAttribute('data-beta-bundled'),authorityType=path==='authority.type';
          if(bundled||authorityType){unit.hidden=true;unit.querySelectorAll('input,select,textarea').forEach(el=>{el.disabled=true;el.required=false;el.setAttribute('aria-required','false');});continue;}
          if(unit===declaration)unit.hidden=false;
          const applicable=!unit.hidden;
          if(applicable&&blocked)unit.hidden=true;
          const visible=!unit.hidden;
          if(visible){
            const label=unit.querySelector('legend')||unit.querySelector('label');if(unit!==declaration)numberLabel(label,++number);
            const missing=unit===declaration?!declarationInput.checked:model.required(path)&&unanswered(path);
            const pair=unit.closest('.ba-native-field-pair');
            if(pair){pairBlocked=pairBlocked||missing;if(unit===pair.lastElementChild){blocked=blocked||pairBlocked;pairBlocked=false;}}
            else if(missing)blocked=true;
          }else unit.querySelectorAll('[data-native-question-number]').forEach(el=>el.remove());
          if(!visible)unit.querySelectorAll('input,select,textarea').forEach(el=>{el.disabled=true;el.required=false;el.setAttribute('aria-required','false');});
          if(unit===authorityWrapper){authorityCanonical.disabled=true;authorityCanonical.required=false;authorityCanonical.setAttribute('aria-required','false');authorityChoice.disabled=!visible||locked;authorityChoice.required=visible&&model.required(path);authorityChoice.setAttribute('aria-required',String(visible&&model.required(path)));}
          if(unit===declaration){declarationInput.disabled=!visible||locked;declarationInput.required=visible;declarationInput.setAttribute('aria-required',String(visible));}
        }
        if(section.dataset.nativeStep==='media'){
          const mediaPanel=section.querySelector('[data-native-media]');
          if(mediaPanel){mediaPanel.hidden=model.effective('media.availability')!=='yes'&&!mediaPanel.querySelector('[data-media-list]')?.children.length;if(!mediaPanel.hidden)numberLabel(mediaPanel.querySelector('label'),++number);}
        }
      }
      for(const group of form.querySelectorAll('fieldset[aria-required]'))if(group.closest('[hidden]'))group.setAttribute('aria-required','false');
      finalBox.hidden=[...finalBox.querySelectorAll('[data-native-field],[data-beta-declarations]')].every(w=>w.hidden);
      for(const section of form.querySelectorAll('[data-native-step]')){
        const inactive=section.dataset.nativeStep!==steps[model.step];
        for(const el of section.querySelectorAll('[data-native-input],.ba-native-card-grid input,[data-beta-confirm],[data-beta-authority]')){
          if(el.hidden||el.disabled||el.closest('[hidden]')){el.required=false;el.setAttribute('aria-required','false');}
          if(inactive){el.disabled=true;el.required=false;el.setAttribute('aria-required','false');}
          else if(el.closest('[data-native-location-fields]')){el.required=false;el.setAttribute('aria-required','false');}
        }
      }
    }
    function numberLabel(label,number){
      if(!label)return;
      const target=label.classList.contains('ba-native-choice')?label.querySelector(':scope>span'):label;
      if(!target)return;
      let badge=target.querySelector('[data-native-question-number]');
      if(!badge){badge=document.createElement('span');badge.dataset.nativeQuestionNumber='';badge.setAttribute('aria-hidden','true');target.prepend(badge);}
      badge.textContent=number+'. ';
    }
    // Pair only closely related fields; DOM and keyboard order remain left-to-right.
    for (const pair of [['reporter.email','reporter.phone'],['reporter.first_name','reporter.last_name'],['vehicle.make','vehicle.model','vehicle.color'],['animal.breed','animal.name'],['time.date','time.knowledge'],['time.range_start','time.range_end'],['property.access_method','property.alarm_status']]) {
      const fields = pair.map(path => wrappers.find(wrapper => wrapper.dataset.nativeField === path));
      if (fields.some(field => !field) || fields.some(field=>field.parentElement!==fields[0].parentElement)) continue;
      const row = document.createElement('div'); row.className = 'ba-native-field-pair';
      row.style.setProperty('--ba-field-columns',String(fields.length));
      fields[0].before(row); fields.forEach(field => row.append(field));
    }
    function expandCards(wrapper) {
      const select = wrapper.querySelector('select[data-native-input]');
      const entry = cardGroups.get(select);
      if (entry) { clearTimeout(entry.commitTimer); entry.collapsedValue = null; syncCards(wrapper,config.fields[wrapper.dataset.nativeField]); }
    }
    function nextQuestion(wrapper) {
      const section = wrapper.closest('[data-native-step]');
      if (!section || section.hidden || model.busy || model.snapshot || model.completed) return;
      const fields = Array.from(section.querySelectorAll('[data-native-field],[data-beta-declarations]'));
      const dependent=fields.find(field=>config.fields[field.dataset.nativeField]?.sourceField===wrapper.dataset.nativeField&&!field.hidden&&!field.closest('[hidden]')&&unanswered(field.dataset.nativeField));
      const nextField = dependent || fields.slice(fields.indexOf(wrapper)+1).find(field => {
        if (field.hidden || field.closest('[hidden]')) return false;
        if(field===declaration)return !declarationInput.checked;
        const path = field.dataset.nativeField, select = field.querySelector('select[data-native-input]');
        const cards = cardGroups.get(select);
        if (cards) return !cards.collapsedValue;
        const value = model.effective(path);
        return value === undefined || value === '' || value === false;
      });
      const target = nextField ? questionTarget(nextField) : form.querySelector('[data-native-next]:not([hidden])');
      if (!target || target.disabled) return;
      target.focus({preventScroll:true});
      // Keep the contextual notice in view when the existing auto-focus advances.
      const anchor = !emergencyNotice.hidden && wrapper.nextElementSibling===emergencyNotice
        ? emergencyNotice : nextField || target;
      const rect = anchor.getBoundingClientRect();
      const headerBottom = document.querySelector('.ba-site-header')?.getBoundingClientRect().bottom || 0;
      const safeTop = Math.max(16,headerBottom+16), bottom = target.getBoundingClientRect().bottom;
      if (rect.top >= safeTop && bottom <= (window.innerHeight || 800)-88) return;
      const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({top:Math.max(0,(window.scrollY || 0)+rect.top-safeTop),behavior:reduce?'auto':'smooth'});
    }
    function syncCards(wrapper, field) {
      const select = wrapper.querySelector('select[data-native-input]');
      if (!select || (field.step === 'location' && wrapper.dataset.nativeField.startsWith('location.')) || wrapper===authorityWrapper || wrapper.dataset.nativeField==='authority.type') return;
      const options = Array.from(select.options).filter(option => option.value);
      const category = wrapper.dataset.nativeField === 'event.category';
      const subtype = wrapper.dataset.nativeField === 'event.subtype';
      if (!category && !subtype && options.length > 6) return;
      let entry = cardGroups.get(select);
      if (!entry) {
        const label = wrapper.querySelector('label');
        label.id = select.id + '-label';
        const group = document.createElement('fieldset');
        group.className = 'ba-native-card-grid' + (category ? ' ba-native-category-grid' : '');
        group.setAttribute('aria-labelledby', label.id);
        group.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        select.after(group); select.hidden = true;
        const edit = document.createElement('button'); edit.type = 'button';
        edit.className = 'ba-native-edit-choice'; edit.textContent = 'Modifica';
        edit.setAttribute('aria-label','Modifica: ' + field.label);
        group.id = select.id + '-choices'; edit.setAttribute('aria-controls',group.id);
        const row = document.createElement('div'); row.className = 'ba-native-choice-row';
        group.before(row); row.append(group,edit);
        entry = {group, edit, signature:null, collapsedValue:null, arrowNavigation:false, commitTimer:null}; cardGroups.set(select, entry);
        edit.addEventListener('click', () => {
          if (select.disabled) return;
          expandCards(wrapper);
          (group.querySelector('input:checked') || group.querySelector('input'))?.focus();
        });
        group.addEventListener('keydown', event => {
          if (event.key.startsWith('Arrow')) entry.arrowNavigation = true;
          if (event.key === 'Enter' && event.target.matches('input[type=radio]')) { event.preventDefault(); event.target.click(); }
        });
        group.addEventListener('keyup', () => { entry.arrowNavigation = false; });

      }
      const signature = JSON.stringify(options.map(option => [option.value,option.textContent]));
      if (entry.signature !== signature) {
        entry.group.replaceChildren(); entry.signature = signature;
        options.forEach(option => {
          const card = document.createElement('label'); card.className = 'ba-native-option-card';
          const radio = document.createElement('input');
          radio.type = 'radio'; radio.name = 'ba-card-' + select.name; radio.value = option.value;
          radio.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
          const copy = document.createElement('span'); copy.className = 'ba-native-card-copy';
          const meta = category ? categoryCards[option.value] : null;
          const title = document.createElement('strong'); title.textContent = meta?.title || option.textContent; copy.append(title);
          if (meta) {
            card.style.setProperty('--ba-card-accent',meta.color);
            const icon = document.createElement('span'); icon.className = 'ba-native-card-icon';
            icon.setAttribute('aria-hidden','true'); icon.innerHTML = cardIcons[meta.icon];
            const description = document.createElement('small'); description.textContent = meta.desc; copy.append(description);
            card.append(icon);
          }
          card.append(radio,copy); entry.group.append(card);
          const commitChoice = () => {
            if (entry.arrowNavigation) return;
            clearTimeout(entry.commitTimer);
            // A task (not a microtask) runs after the browser's radio default action
            // has dispatched input/change and the canonical select is up to date.
            entry.commitTimer = setTimeout(() => {
              if (disposed || select.disabled || entry.arrowNavigation || select.value !== radio.value || wrapper.closest('[data-native-step]').hidden) return;
              entry.collapsedValue = select.value; syncCards(wrapper,field);
              nextQuestion(wrapper);
            },0);
          };
          const chooseCard = event => {
            event.stopPropagation();
            if (select.disabled) return;
            select.value = radio.value;
            select.dispatchEvent(new Event(event.type,{bubbles:true}));
            syncCards(wrapper, field); // Reconcile a rejected change with the canonical control.
            if (event.type === 'change') commitChoice();
          };
          radio.addEventListener('input',chooseCard);
          radio.addEventListener('change',chooseCard);
          radio.addEventListener('click',commitChoice);
        });
      }
      if (!select.value || entry.collapsedValue !== select.value) entry.collapsedValue = null;
      const collapsed = Boolean(entry.collapsedValue);
      entry.group.classList.toggle('is-collapsed',collapsed);
      entry.edit.hidden = !collapsed; entry.edit.disabled = select.disabled;
      entry.edit.setAttribute('aria-expanded',String(!collapsed));
      entry.group.disabled = select.disabled;
      entry.group.querySelectorAll('input').forEach(radio => {
        radio.checked = radio.value === select.value;
        radio.closest('label').hidden = collapsed && !radio.checked;
        radio.disabled = select.disabled || (collapsed && !radio.checked); radio.required = select.required && !radio.disabled;
        radio.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
      });
    }
    function render(focus = false) {
      if(disposed)return;
      model.pruneInactive();
      const current = steps[model.step];
      if (publicLocationChoice) {
        publicLocationChoice.querySelectorAll('input[type="radio"]').forEach(input=>{
          input.checked=input.value===model.publicLocationRequest;
          input.disabled=model.busy || !!model.snapshot || model.completed;
        });
      }
      if(radiusField){const active=model.publicLocationRequest==='approximate_area';radiusField.hidden=!active;radiusSelect.disabled=!active||model.busy||!!model.snapshot||model.completed;radiusSelect.required=active;radiusSelect.value=active?String(model.publicLocationRadius??''):'';}
      const subtype = form.querySelector('[name="event.subtype"]');
      const previous = model.effective('event.subtype') || '';
      subtype.replaceChildren(new Option('Seleziona…',''));
      Object.entries(config.categories[model.values['event.category']]?.subtypes || {}).forEach(([value,label]) => subtype.add(new Option(label,value)));
      subtype.value = previous;
      wrappers.forEach(wrapper => {
        const path = wrapper.dataset.nativeField, field = config.fields[path], active = model.active(path);
        if (path === 'damage.status') {
          const theft = model.effective('event.subtype') === 'vehicle_stolen';
          const label = wrapper.querySelector('label');
          const copyNode=[...label.childNodes].find(node=>node.nodeType===3);
          if (copyNode) copyNode.textContent = theft ? 'Ci sono danni aggiuntivi legati al furto?' : field.label;
          wrapper.querySelector('.ba-native-help').textContent = theft
            ? 'Indica eventuali danni materiali oltre al furto, per esempio a serrature o vetri. Se il mezzo non è stato ritrovato e non puoi verificarli, scegli «Da verificare».'
            : field.help;
        }
        // Mirror pruned model values into canonical controls and their card adapters.
        if(config.conditional)wrapper.querySelectorAll('[data-native-input]').forEach(input=>{
          const value=model.effective(path);
          if(input.type==='checkbox')input.checked=field.type==='array'?Array.isArray(value)&&value.includes(input.value):value===true;
          else input.value=value===undefined?'':String(value);
        });
        if(config.conditional && field.constraints.enum && path!=='event.subtype'){
          const select=wrapper.querySelector('select[data-native-input]');
          let allowed=model.constraints(path).enum;
          if(path==='event.category'){
            const presentationOrder=['vehicle','property','hazard','public_space','animal','item_document'];
            allowed=[...allowed].sort((a,b)=>presentationOrder.indexOf(a)-presentationOrder.indexOf(b));
          }
          if(select){const value=model.effective(path)||'';select.replaceChildren(new Option('Seleziona…',''));for(const option of allowed)select.add(new Option(field.options[option]||option,option));select.value=value;}
        }
        const deferred = (path === 'event.subtype' && !model.values['event.category']) || (path === 'reporter.relationship' && !model.effective('event.subtype'));
        wrapper.hidden = !active || deferred;
        const inputs = wrapper.querySelectorAll('[data-native-input]');
        inputs.forEach(input => {
          input.disabled = !active || deferred || model.busy || !!model.snapshot || model.completed;
          input.required = active && model.required(path) && field.type !== 'array';
          input.setAttribute('aria-required', String(active && model.required(path) && field.type !== 'array'));
          const group = wrapper.querySelector('fieldset');
          if (group) group.setAttribute('aria-required', String(active && model.required(path)));
        });
        const flag = wrapper.querySelector('[data-native-required]'); if (flag) flag.textContent = active && model.required(path) ? ' (obbligatorio)' : ' (facoltativo)';
        syncCards(wrapper, field);
      });
      form.querySelectorAll('[data-native-step]').forEach(step => { step.hidden = step.dataset.nativeStep !== current; });
      container.querySelectorAll('[data-native-progress]').forEach((li,index) => {
        li.classList.toggle('is-active', index === model.step);
        li.classList.toggle('is-complete', index < model.step);
        if (index === model.step) li.setAttribute('aria-current','step'); else li.removeAttribute('aria-current');
      });
      const position = container.querySelector('[data-native-position]');
      if (position) {
        const text = `Sei in: ${config.steps[current]}.${model.step<steps.length-1?' Dopo: '+config.steps[steps[model.step+1]]+'.':' Controlla e invia la segnalazione.'}`;
        if (position.textContent !== text) position.textContent = text;
      }
      back.disabled = model.step === 0 || model.busy || !!model.snapshot;
      next.hidden = current === 'review'; next.disabled = model.busy || !!model.snapshot;
      submit.hidden = current !== 'review'; submit.disabled = model.busy || model.completed || !!model.blocked;
      submit.textContent = model.busy ? 'Invio in corso…' : model.snapshot ? 'Riprova invio' : 'Invia segnalazione';
      form.setAttribute('aria-busy', String(model.busy));
      syncSubmissionFeedback();
      smartAddress?.syncVisibility?.();
      if (mediaView) mediaView.render();
      syncFlow();
      const specifications=new Set(wrappers.filter(w=>config.fields[w.dataset.nativeField].sourceField&&model.active(w.dataset.nativeField)).map(w=>w.dataset.nativeField));
      const added=[...specifications].filter(path=>!activeSpecifications.has(path));
      if(added.length)conditionalStatus.textContent=added.map(path=>config.fields[path].label+' — campo obbligatorio.').join(' ');
      else if(specifications.size<activeSpecifications.size)conditionalStatus.textContent='Domande aggiornate in base alle risposte.';
      activeSpecifications=specifications;
      syncEmergencyNotice(current);
      if (current === 'review') review();
      if (focus) focusStep(current);
    }
    function focusStep(current) {
      const section = form.querySelector(`[data-native-step="${current}"]`);
      const heading = section?.querySelector('h2');
      if (!section || !heading) return;
      try { heading.focus({preventScroll:true}); } catch (_) { heading.focus(); }
      const rect = section.getBoundingClientRect();
      const header = document.querySelector('.ba-site-header');
      const headerBottom = header ? header.getBoundingClientRect().bottom : 0;
      const safeTop = Math.max(16, headerBottom + 16);
      const safeBottom = Math.max(safeTop + 120, (window.innerHeight || document.documentElement.clientHeight || 0) - 24);
      if (rect.top >= safeTop && rect.top <= safeBottom) return;
      const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const top = Math.max(0, (window.scrollY || window.pageYOffset || 0) + rect.top - safeTop);
      if (typeof window.scrollTo === 'function') {
        try { window.scrollTo({top,behavior:reduceMotion ? 'auto' : 'smooth'}); }
        catch (_) { window.scrollTo(0,top); }
      }
    }
    function displayErrors(errors) {
      // Keep the canonical validator untouched, but do not surface errors for
      // questions that progressive disclosure has not shown to the user yet.
      errors=errors.filter(error=>{
        if(!config.fields[error.field])return true;
        if(!model.active(error.field))return false;
        if(declarationPaths.includes(error.field))return true;
        if(error.field==='location.public_location_request.radius_m')return model.publicLocationRequest==='approximate_area';
        const wrapper=wrappers.find(w=>w.dataset.nativeField===error.field);
        return !wrapper || (!wrapper.hidden && !wrapper.closest('[hidden]'));
      });
      const first = errors.find(error => config.fields[error.field] && model.active(error.field));
      if (first) model.step = steps.indexOf(config.fields[first.field].step);
      if(errors.some(e=>e.field==='location.public_location_request.radius_m'))model.step=steps.indexOf('location');
      render();
      const declarationMissing=errors.some(error=>declarationPaths.includes(error.field));
      declarationError.hidden=!declarationMissing;declarationError.textContent=declarationMissing?'Conferma le dichiarazioni prima di proseguire.':'';
      declarationInput.setAttribute('aria-invalid',String(declarationMissing));
      authorityChoice.setAttribute('aria-invalid',String(errors.some(error=>error.field==='authority.status')));
      // One visible confirmation needs one actionable error, while canonical validation stays separate.
      const declarationErrorIndex=errors.findIndex(error=>declarationPaths.includes(error.field));
      const visibleErrors=errors.filter((error,index)=>!declarationPaths.includes(error.field)||index===declarationErrorIndex);
      ns.presentErrors(container, visibleErrors, config, (path, file) => {
        if(path==='location.public_location_request.radius_m'){model.step=steps.indexOf('location');render();radiusSelect?.focus();return;}
        model.step = steps.indexOf(config.fields[path].step); render();
        if (config.fields[path].step === 'location' && path.startsWith('location.') && !config.fields[path].sourceField) {
          focusStep('location');
          const start = container.querySelector('[data-smart-start]');
          const search = start && !start.hidden ? start : container.querySelector('[data-smart-widget]')?.firstElementChild;
          if (search && !search.disabled) search.focus();
          return;
        }
        if (file && mediaView) return mediaView.focus(file);
        const wrapper = wrappers.find(w => w.dataset.nativeField === path);
        if(declarationPaths.includes(path)){declarationInput.focus();return;}
        if(path==='authority.status'){authorityChoice.focus();return;}
        expandCards(wrapper);
        (wrapper.querySelector('.ba-native-card-grid input:checked') || wrapper.querySelector('.ba-native-card-grid input:not(:disabled)') || wrapper.querySelector('input,select,textarea,fieldset')).focus();
      });
      if(declarationMissing){
        const index=visibleErrors.findIndex(error=>declarationPaths.includes(error.field));
        const item=container.querySelectorAll('[data-native-errors] li')[index];
        const button=document.createElement('button');button.type='button';button.textContent='Prima di inviare: conferma le dichiarazioni per proseguire.';
        button.addEventListener('click',()=>{model.step=steps.indexOf('contact');render();declarationInput.focus();});item.replaceChildren(button);
      }
    }
    wrappers.forEach(wrapper => {
      const path = wrapper.dataset.nativeField;
      wrapper.querySelectorAll('[data-native-input]').forEach(input => {
        if (model.values[path] !== undefined) {
          if (input.type === 'checkbox') input.checked = model.values[path] === true; else input.value = model.values[path];
        }
      });
    });
    mountMedia();
    if (publicLocationChoice) {
      listen(publicLocationChoice,'change',event=>{
        if(event.target===radiusSelect)model.setPublicLocationRadius(Number(event.target.value));
        else if(event.target.matches('input[type="radio"]'))model.setPublicLocationRequest(event.target.value);
        else return;
        ns.presentErrors(container,[],config);render();
      });
    }
    listen(form,'input', event => {
      const wrapper = event.target.closest('[data-native-field]'); if (!wrapper) return;
      model.set(wrapper.dataset.nativeField, read(wrapper));
      ns.presentErrors(container, [], config); render();
    });
    // Some select/checkbox assistive technology emits change without input.
    listen(form,'change', event => {
      const wrapper = event.target.closest('[data-native-field]'); if (!wrapper) return;
      model.set(wrapper.dataset.nativeField, read(wrapper)); ns.presentErrors(container, [], config); render();
    });
    listen(next,'click', () => {
      if(model.busy || model.snapshot || model.completed || model.step>=steps.length-1)return;
      if (steps[model.step] === 'location' && smartAddress && !smartAddress.canAdvance()) return;
      const errors = model.validate(steps[model.step]);
      if (errors.length) return displayErrors(errors);
      model.step++; ns.presentErrors(container, [], config); render(true);
    });
    listen(back,'click', () => { if (model.step > 0 && !model.snapshot) { model.step--; ns.presentErrors(container, [], config); render(true); } });
    listen(form,'submit', async event => {
      event.preventDefault();
      if (steps[model.step] !== 'review' || model.busy || model.completed || model.blocked) return;
      if (!model.snapshot) {
        const mediaErrors=mediaView?.media.errors() || []; if (mediaErrors.length) return displayErrors(mediaErrors);
        const errors = model.validate(); if (errors.length) return displayErrors(errors);
        const payload=model.payload();
        const descriptors=mediaView ? mediaView.media.freeze() : [];
        if (descriptors.length) { payload.media.items=descriptors;model.snapshot=ns.freezeMediaPayload(payload); }
        else model.snapshot=payload;
      }
      model.busy = true; ns.presentErrors(container, [], config); render();
      const result = await ns.send(config, model.snapshot, undefined, undefined, mediaView?.media.reportCapability());
      if(disposed)return;
      if (result.ok) {
        model.busy = false;
        model.completed = true; model.lastError = null;
        form.setAttribute('aria-busy','false');syncSubmissionFeedback();
        mediaView?.dispose();
        form.hidden = true; container.querySelector('nav').hidden = true; success.hidden = false;
        const heading=success.querySelector('h2');
        try{heading.focus({preventScroll:true});}catch(_){heading.focus();}
        const revealConfirmation=()=>{
          if(disposed||!model.completed)return;
          const header=document.querySelector('.ba-site-header');
          const safeTop=Math.max(24,(header?.getBoundingClientRect().bottom||0)+24);
          const top=Math.max(0,(window.scrollY||0)+success.getBoundingClientRect().top-safeTop);
          window.scrollTo({top,behavior:'instant'});
        };
        revealConfirmation();
        window.requestAnimationFrame?.(revealConfirmation);
      } else {
        model.lastError = result.error; // Code retained for troubleshooting, no payload/PII logging.
        const validationRejection = result.error.httpStatus === 422 && !result.error.retryable
          && correctableCodes.has(result.error.code) && config.fields[result.error.field] && model.active(result.error.field);
        const mediaBearing=!!model.snapshot.media?.items?.length;
        const editableMedia=mediaBearing && await mediaView.media.editable(result.error,validationRejection);
        if(disposed)return;
        model.busy = false; // Includes owner-status reconciliation in the double-submit guard.
        if (mediaBearing) {
          if (editableMedia) { model.snapshot=null;mediaView.media.unlock(); }
          else { if(result.error.retryable)mediaView.media.markUncertain();else model.blocked=true; }
        } else if (validationRejection) { model.snapshot = null;mediaView?.media.unlock(); }
        else if (!result.error.retryable) model.blocked = true;
        // A locked snapshot must stay on review, where an allowed retry is reachable.
        displayErrors([model.snapshot ? {...result.error, field:null} : editableMedia ? {...result.error,field:config.fields[result.error.field] ? result.error.field : 'media.availability'} : result.error]);
      }
    });
    listen(container.querySelector('[data-native-new]'),'click', () => {
      mediaView?.dispose();
      cardGroups.forEach(entry => { clearTimeout(entry.commitTimer); entry.collapsedValue = null; });
      form.reset(); model.reset();clearReporterChoices(); model.blocked = false; model.lastError = null;
       smartAddress?.reset?.();
      wrappers.forEach(wrapper => {
        const value = model.values[wrapper.dataset.nativeField];
        if (value !== undefined) wrapper.querySelector('[data-native-input]').value = value;
      });
      mountMedia();
      success.hidden = true; form.hidden = false; container.querySelector('nav').hidden = false;
      ns.presentErrors(container, [], config); render(true);
    });
    container.querySelector('[data-native-boot]').hidden = true;
    form.querySelector('[data-native-navigation]').hidden = false;
    render();
    // Object stays in memory only: useful for QA, no local/session storage.
    container.nativeReport = {model, config, teardown};
    listen(window,'pagehide',event=>{if(!event.persisted)teardown();});
  }
  document.querySelectorAll('[data-native-report]').forEach(container => {
    try { boot(container); } catch (_) {
      container.querySelector('[data-native-boot]').textContent = 'Il modulo non può essere avviato. Ricarica la pagina o riprova più tardi.';
    }
  });
})();
