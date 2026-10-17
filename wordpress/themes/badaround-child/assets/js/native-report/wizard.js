(function () {
  'use strict';
  const ns = window.BadAroundNative;
  function boot(container) {
    if (container.nativeReport) return; // Reinitialization must retain the current logical wizard.
    let disposed=false; const listeners=[];
    function listen(target,type,handler) { target.addEventListener(type,handler);listeners.push(()=>target.removeEventListener(type,handler)); }
    function teardown() { if(disposed)return; disposed=true;listeners.forEach(remove=>remove());mediaView?.dispose();smartAddress?.dispose(); }
    const config = JSON.parse(container.querySelector('[data-native-config]').textContent);
    const model = new ns.Model(config);
    const form = container.querySelector('[data-native-form]');
    const steps = Object.keys(config.steps);
    const wrappers = Array.from(form.querySelectorAll('[data-native-field]'));
    const next = form.querySelector('[data-native-next]'), back = form.querySelector('[data-native-back]'), submit = form.querySelector('[data-native-submit]');
    const success = container.querySelector('[data-native-success]');
    let mediaView = null;
    const smartAddress = ns.SmartAddress?.mount(container, config, model);
    function mountMedia() {
      if (ns.mountMedia && config.media) mediaView = ns.mountMedia(container, model, config.media, () => render());
    }
    // Presentation classification of frozen F1.3 errors, never a validator.
    const correctableCodes = new Set(['invalid_type', 'missing_required_field', 'conditional_field_required', 'invalid_email', 'invalid_phone', 'invalid_date_time', 'invalid_enum', 'invalid_category_subtype', 'invalid_location', 'invalid_plate', 'invalid_boolean', 'invalid_array', 'invalid_number', 'value_too_short', 'value_too_long', 'value_too_small', 'value_too_large', 'reward_amount_required', 'reward_confirmation_required']);
    const read = wrapper => {
      const field = config.fields[wrapper.dataset.nativeField];
      const inputs = wrapper.querySelectorAll('[data-native-input]');
      return field.type === 'array' ? Array.from(inputs).filter(i => i.checked).map(i => i.value) : field.type === 'bool' ? inputs[0].checked : inputs[0].value;
    };
    function review() {
      const region = form.querySelector('[data-native-review]'); region.replaceChildren();
      const sections = new Map();
      Object.entries(config.fields).forEach(([path, field]) => {
        if (!model.active(path)) return;
        // The review is user-facing: never print canonical territory metadata or coordinates.
        if (field.step === 'location' && path !== 'location.exact_address') return;
        const value = model.effective(path); if (value === undefined || value === '') return;
        const stepKey = field.step || 'review';
        let section = sections.get(stepKey);
        if (!section) {
          section = document.createElement('section');
          section.className = 'ba-native-review-section';
          const heading = document.createElement('h3');
          heading.textContent = config.steps[stepKey] || 'Riepilogo';
          const list = document.createElement('dl');
          section.append(heading, list);
          sections.set(stepKey, section);
          region.append(section);
        }
        const list = section.querySelector('dl');
        const term = document.createElement('dt'); term.textContent = field.label;
        const detail = document.createElement('dd');
        const label = v => field.options[v] || String(v);
        const valueText = typeof value === 'boolean'
          ? (value ? 'Confermato' : 'Non confermato')
          : Array.isArray(value) ? value.map(label).join(', ') : label(value);
        const valueNode = document.createElement('span'); valueNode.textContent = valueText;
        detail.append(valueNode);
        if (field.help) {
          const help = document.createElement('small');
          help.className = 'ba-native-review-help';
          help.textContent = field.help;
          detail.append(help);
        }
        list.append(term, detail);
      });
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
        if(unresolved){const pending=document.createElement('p');pending.className='ba-native-error';pending.textContent=`${unresolved} immagini da completare o rimuovere nello Step 5 prima dell’invio.`;mediaSection.append(pending);}
        region.append(mediaSection);
      }
    }
    // Native presentation only: selects remain the sole canonical model inputs.
    const categoryCards = {
      vehicle: {title:'Veicoli', desc:'Auto, moto e altri mezzi', icon:'vehicle', color:'#1688e8'},
      property: {title:'Case e attività', desc:'Abitazioni, negozi e uffici', icon:'property', color:'#9c3152'},
      hazard: {title:'Pericoli', desc:'Rischi e situazioni pericolose', icon:'hazard', color:'#e36b21'},
      public_space: {title:'Spazi pubblici', desc:'Strade, aree pubbliche e decoro', icon:'public', color:'#087f8c'},
      animal: {title:'Animali', desc:'Smarrimenti e segnalazioni', icon:'animal', color:'#7e3db6'},
      item_document: {title:'Oggetti e documenti', desc:'Oggetti smarriti o ritrovati', icon:'object', color:'#a26f14'}
    };
    const cardIcons = {
		vehicle:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 14h15l-1.8-5.2a2 2 0 0 0-1.9-1.3H8.2a2 2 0 0 0-1.9 1.3L4.5 14Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 14v4m14-4v4M7.5 18h9M7.2 12h.01M16.8 12h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
		property:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-7 9 7M6 9.5V20h12V9.5M9 20v-6h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
		hazard:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3.5 20h17L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
		public:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9l8-5 8 5v11M2 20h20M8 13h8M9 17h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
		animal:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12c-2 0-3.5 1.4-3.5 3.2C4.5 18 7.6 20 12 20s7.5-2 7.5-4.8C19.5 13.4 18 12 16 12m-8 0c.7-2.1 2.1-3.3 4-3.3s3.3 1.2 4 3.3M6.5 8.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM12 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
		object:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7h8l2 3v9H6v-9l2-3Zm1 0V5h6v2M9 12h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>'
    };
    const cardGroups = new Map();
    function syncCards(wrapper, field) {
      const select = wrapper.querySelector('select[data-native-input]');
      if (!select || field.step === 'location') return;
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
        entry = {group, signature:null}; cardGroups.set(select, entry);
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
          const chooseCard = event => {
            event.stopPropagation();
            if (select.disabled) return;
            select.value = radio.value;
            select.dispatchEvent(new Event(event.type,{bubbles:true}));
            syncCards(wrapper, field); // Reconcile a rejected change with the canonical control.
          };
          radio.addEventListener('input',chooseCard);
          radio.addEventListener('change',chooseCard);
        });
      }
      entry.group.disabled = select.disabled;
      entry.group.querySelectorAll('input').forEach(radio => {
        radio.checked = radio.value === select.value;
        radio.disabled = select.disabled; radio.required = select.required;
        radio.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
      });
    }
    function render(focus = false) {
      if(disposed)return;
      const current = steps[model.step];
      const subtype = form.querySelector('[name="event.subtype"]');
      const previous = model.effective('event.subtype') || '';
      subtype.replaceChildren(new Option('Seleziona…',''));
      Object.entries(config.categories[model.values['event.category']]?.subtypes || {}).forEach(([value,label]) => subtype.add(new Option(label,value)));
      subtype.value = previous;
      wrappers.forEach(wrapper => {
        const path = wrapper.dataset.nativeField, field = config.fields[path], active = model.active(path);
        const deferred = (path === 'event.subtype' && !model.values['event.category']) || (path === 'reporter.relationship' && !model.effective('event.subtype'));
        wrapper.hidden = !active || deferred;
        const inputs = wrapper.querySelectorAll('[data-native-input]');
        inputs.forEach(input => {
          input.disabled = !active || deferred || model.busy || !!model.snapshot || model.completed;
          input.required = active && field.required && field.type !== 'array';
          input.setAttribute('aria-required', String(active && field.required && field.type !== 'array'));
          const group = wrapper.querySelector('fieldset');
          if (group) group.setAttribute('aria-required', String(active && field.required));
        });
        const flag = wrapper.querySelector('[data-native-required]'); if (flag) flag.textContent = active && field.required ? ' (obbligatorio)' : ' (facoltativo)';
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
        const text = `Passaggio ${model.step + 1} di ${steps.length}: ${config.steps[current]}`;
        if (position.textContent !== text) position.textContent = text;
      }
      back.disabled = model.step === 0 || model.busy || !!model.snapshot;
      next.hidden = current === 'review'; next.disabled = model.busy || !!model.snapshot;
      submit.hidden = current !== 'review'; submit.disabled = model.busy || model.completed || !!model.blocked;
      submit.textContent = model.busy ? 'Invio in corso…' : model.snapshot ? 'Riprova invio' : 'Invia segnalazione';
      form.setAttribute('aria-busy', String(model.busy));
      smartAddress?.syncVisibility?.();
      if (mediaView) mediaView.render();
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
      const first = errors.find(error => config.fields[error.field] && model.active(error.field));
      if (first) model.step = steps.indexOf(config.fields[first.field].step);
      render(); ns.presentErrors(container, errors, config, (path, file) => {
        model.step = steps.indexOf(config.fields[path].step); render();
        if (config.fields[path].step === 'location') {
          focusStep('location');
          const start = container.querySelector('[data-smart-start]');
          const search = start && !start.hidden ? start : container.querySelector('[data-smart-widget]')?.firstElementChild;
          if (search && !search.disabled) search.focus();
          return;
        }
        if (file && mediaView) return mediaView.focus(file);
        const wrapper = wrappers.find(w => w.dataset.nativeField === path);
        (wrapper.querySelector('.ba-native-card-grid input:not(:disabled)') || wrapper.querySelector('input,select,textarea,fieldset')).focus();
      });
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
        mediaView?.dispose();
        form.hidden = true; container.querySelector('nav').hidden = true; success.hidden = false;
        success.querySelector('h2').focus();
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
      form.reset(); model.reset(); model.blocked = false; model.lastError = null;
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
