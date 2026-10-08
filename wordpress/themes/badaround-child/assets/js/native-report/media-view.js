(function (root) {
  'use strict';
  const ns=root.BadAroundNative=root.BadAroundNative || {};
  ns.mountMedia=function(container,model,config,changed) {
    const panel=container.querySelector('[data-native-media]'), picker=panel.querySelector('input[type=file]');
    const list=panel.querySelector('[data-media-list]'), notice=panel.querySelector('[data-media-notice]'), live=panel.querySelector('[data-media-live]');
    const nodes=new Map(); let media;
    function message(code) { notice.textContent=ns.errorMessage({code});notice.hidden=false; }
    function render() {
      const items=media.items, busy=items.some(r=>['uploading','removing'].includes(r.state));
      const disabled=model.busy || !!model.snapshot || model.completed || media.locked;
      picker.disabled=disabled || media.count>=5 || model.effective('media.availability')!=='yes';
      panel.querySelector('[data-media-count]').textContent=`${media.descriptors().length} immagini accettate · ${items.length}/5 selezionate`;
      const codecs=media.supported;
      panel.querySelector('[data-media-formats]').textContent=codecs ? 'Formati disponibili: '+codecs.map(c=>c==='jpeg'?'JPEG':c.toUpperCase()).join(', ')+'. HEIC/HEIF non supportati.' : 'JPEG, PNG e WebP statico se disponibili sul servizio. HEIC/HEIF non supportati.';
      if (codecs) picker.accept=codecs.map(c=>'image/'+c).join(',');
      let lostFocus=false;
      for (const [id,node] of nodes) if (!items.some(r=>r.id===id)) { lostFocus=lostFocus || node.contains(document.activeElement);node.remove();nodes.delete(id); }
      items.forEach(row=>{
        let node=nodes.get(row.id);
        if (!node) {
          node=document.createElement('li');node.className='ba-native-media-card';node.id='ba-media-'+row.id;node.tabIndex=-1;
          const image=document.createElement('img');image.alt='Anteprima locale';image.loading='lazy';
          const content=document.createElement('div');content.className='ba-native-media-content';
          const name=document.createElement('strong');name.dataset.mediaName='';
          const state=document.createElement('p');state.dataset.mediaState='';
          const progress=document.createElement('progress');progress.max=100;progress.setAttribute('aria-label','Caricamento di '+row.name);
          const error=document.createElement('p');error.className='ba-native-error';error.id=node.id+'-error';error.dataset.mediaError='';
          const actions=document.createElement('div');actions.className='ba-native-media-actions';
          for (const [action,label] of [['retry','Riprova'],['remove','Rimuovi']]) {
            const button=document.createElement('button');button.type='button';button.className='ba-button ba-button--outline';button.textContent=label;button.dataset.mediaAction=action;button.setAttribute('aria-label',label+' '+row.name);button.setAttribute('aria-describedby',error.id);
            button.addEventListener('click',()=>{notice.hidden=true;media[action](row.id);});actions.append(button);
          }
          content.append(name,state,progress,error,actions);node.append(image,content);nodes.set(row.id,node);list.append(node);
        }
        node.querySelector('img').src=row.preview;node.querySelector('[data-media-name]').textContent=row.name;
        node.querySelector('[data-media-state]').textContent=({selected:'In attesa di caricamento',uploading:'Caricamento in corso',accepted:'Accettata e riservata',failed:'Caricamento non completato',removing:'Rimozione in corso'})[row.state]+(row.state==='uploading' && row.progress!==null ? ` — ${row.progress}%` : '');
        const progress=node.querySelector('progress');progress.hidden=row.state!=='uploading';
        if (row.progress===null) progress.removeAttribute('value'); else progress.value=row.progress;
        const error=node.querySelector('[data-media-error]');error.hidden=!row.error;error.textContent=row.error ? ns.errorMessage({code:row.error}) : '';
        node.querySelector('[data-media-action=retry]').hidden=row.state!=='failed';
        node.querySelectorAll('button').forEach(button=>{button.disabled=disabled || busy;});
      });
      if (lostFocus) {
        const target=list.querySelector('button:not([disabled]):not([hidden])') || (!picker.disabled ? picker : container.querySelector('[name="media.availability"]'));
        target.focus();
      }
    }
    media=new ns.Media(config,model.submissionId,{changed:action=>{
      if (media.count && !model.snapshot) model.set('media.availability','yes');
      if (action==='removed' && !media.count && !model.snapshot) model.set('media.availability','no');
      const input=container.querySelector('[name="media.availability"]');input.value=model.effective('media.availability') || '';
      render();
      if (['accepted','failed','removed','remove_failed'].includes(action)) live.textContent=action==='removed' ? 'Immagine rimossa.' : action==='accepted' ? 'Caricamento confermato.' : 'Operazione non completata. Controlla le immagini.';
      changed();
    }});
    const select=()=>{notice.hidden=true;const errors=media.select(Array.from(picker.files));picker.value='';if(errors.length){notice.textContent=errors.map(error=>error.name+': '+ns.errorMessage(error)).join(' ');notice.hidden=false;}};
    picker.addEventListener('change',select);
    const availability=container.querySelector('[name="media.availability"]');
    function guard(event) {
      if (media.count && availability.value!=='yes') { availability.value=model.effective('media.availability') || 'yes';message('media_remove_first');event.stopImmediatePropagation(); }
    }
    // Run before the generic model listener, including assistive-technology change-only events.
    availability.addEventListener('input',guard);availability.addEventListener('change',guard);
    render();
    return {media,render,focus:id=>nodes.get(id)?.focus(),dispose:()=>{
      picker.removeEventListener('change',select);availability.removeEventListener('input',guard);availability.removeEventListener('change',guard);
      media.dispose();list.replaceChildren();notice.hidden=true;
    }};
  };
})(typeof window!=='undefined' ? window : globalThis);
