(function () {
  'use strict';
  const ns = window.BadAroundNative;
  function boot(container) {
    const config = JSON.parse(container.querySelector('[data-native-config]').textContent);
    const model = new ns.Model(config);
    const form = container.querySelector('[data-native-form]');
    const steps = Object.keys(config.steps);
    const wrappers = Array.from(form.querySelectorAll('[data-native-field]'));
    const next = form.querySelector('[data-native-next]'), back = form.querySelector('[data-native-back]'), submit = form.querySelector('[data-native-submit]');
    const success = container.querySelector('[data-native-success]');
    let mediaView = null;
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
      const list = document.createElement('dl');
      Object.entries(config.fields).forEach(([path, field]) => {
        if (!model.active(path)) return;
        const value = model.effective(path); if (value === undefined || value === '') return;
        const term = document.createElement('dt'); term.textContent = field.label;
        const detail = document.createElement('dd');
        const label = v => field.options[v] || String(v);
        detail.textContent = (typeof value === 'boolean' ? (value ? 'Confermato' : 'Non confermato') : Array.isArray(value) ? value.map(label).join(', ') : label(value)) + ' — ' + field.help;
        list.append(term, detail);
      });
      region.append(list);
      if (mediaView && mediaView.media.count) {
        const title=document.createElement('p');title.textContent=`Immagini accettate: ${mediaView.media.descriptors().length}. Gli originali restano riservati.`;
        const previews=document.createElement('ul');previews.className='ba-native-review-media';
        mediaView.media.items.forEach(row=>{
          const li=document.createElement('li'), img=document.createElement('img'), state=document.createElement('span');
          img.src=row.preview;img.alt='Anteprima locale';state.textContent=row.state==='accepted' ? 'Accettata' : 'Da completare o rimuovere';li.append(img,state);previews.append(li);
        });region.append(title,previews);
      }
    }
    function render(focus = false) {
      const current = steps[model.step];
      const subtype = form.querySelector('[name="event.subtype"]');
      const previous = model.effective('event.subtype') || '';
      subtype.replaceChildren(new Option('Seleziona…',''));
      Object.entries(config.categories[model.values['event.category']]?.subtypes || {}).forEach(([value,label]) => subtype.add(new Option(label,value)));
      subtype.value = previous;
      wrappers.forEach(wrapper => {
        const path = wrapper.dataset.nativeField, field = config.fields[path], active = model.active(path);
        wrapper.hidden = !active;
        const inputs = wrapper.querySelectorAll('[data-native-input]');
        inputs.forEach(input => {
          input.disabled = !active || model.busy || !!model.snapshot || model.completed;
          input.required = active && field.required && field.type !== 'array';
          input.setAttribute('aria-required', String(active && field.required && field.type !== 'array'));
          const group = wrapper.querySelector('fieldset');
          if (group) group.setAttribute('aria-required', String(active && field.required));
        });
        const flag = wrapper.querySelector('[data-native-required]'); if (flag) flag.textContent = active && field.required ? ' (obbligatorio)' : '';
      });
      form.querySelectorAll('[data-native-step]').forEach(step => { step.hidden = step.dataset.nativeStep !== current; });
      container.querySelectorAll('[data-native-progress]').forEach((li,index) => {
        li.classList.toggle('is-active', index === model.step);
        li.classList.toggle('is-complete', index < model.step);
        if (index === model.step) li.setAttribute('aria-current','step'); else li.removeAttribute('aria-current');
      });
      back.disabled = model.step === 0 || model.busy || !!model.snapshot;
      next.hidden = current === 'review'; next.disabled = model.busy || !!model.snapshot;
      submit.hidden = current !== 'review'; submit.disabled = model.busy || model.completed || !!model.blocked;
      submit.textContent = model.busy ? 'Invio in corso…' : model.snapshot ? 'Riprova invio' : 'Invia segnalazione';
      form.setAttribute('aria-busy', String(model.busy));
      if (mediaView) mediaView.render();
      if (current === 'review') review();
      if (focus) form.querySelector(`[data-native-step="${current}"] h2`).focus();
    }
    function displayErrors(errors) {
      const first = errors.find(error => config.fields[error.field] && model.active(error.field));
      if (first) model.step = steps.indexOf(config.fields[first.field].step);
      render(); ns.presentErrors(container, errors, config, (path, file) => {
        model.step = steps.indexOf(config.fields[path].step); render();
        if (file && mediaView) return mediaView.focus(file);
        const wrapper = wrappers.find(w => w.dataset.nativeField === path);
        wrapper.querySelector('input,select,textarea,fieldset').focus();
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
    form.addEventListener('input', event => {
      const wrapper = event.target.closest('[data-native-field]'); if (!wrapper) return;
      model.set(wrapper.dataset.nativeField, read(wrapper));
      ns.presentErrors(container, [], config); render();
    });
    // Some select/checkbox assistive technology emits change without input.
    form.addEventListener('change', event => {
      const wrapper = event.target.closest('[data-native-field]'); if (!wrapper) return;
      model.set(wrapper.dataset.nativeField, read(wrapper)); render();
    });
    next.addEventListener('click', () => {
      const errors = model.validate(steps[model.step]);
      if (errors.length) return displayErrors(errors);
      model.step++; ns.presentErrors(container, [], config); render(true);
    });
    back.addEventListener('click', () => { if (model.step > 0 && !model.snapshot) { model.step--; ns.presentErrors(container, [], config); render(true); } });
    form.addEventListener('submit', async event => {
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
    container.querySelector('[data-native-new]').addEventListener('click', () => {
      mediaView?.dispose();
      form.reset(); model.reset(); model.blocked = false; model.lastError = null;
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
    container.nativeReport = {model, config, teardown:()=>mediaView?.dispose()};
    window.addEventListener('pagehide',event=>{if(!event.persisted)mediaView?.dispose();});
  }
  document.querySelectorAll('[data-native-report]').forEach(container => {
    try { boot(container); } catch (_) {
      container.querySelector('[data-native-boot]').textContent = 'Il modulo non può essere avviato. Torna al modulo di riferimento.';
    }
  });
})();
