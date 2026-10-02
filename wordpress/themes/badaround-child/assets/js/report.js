(() => {
	'use strict';

	const form = document.querySelector('#wpforms-6');
	if (!form) return;

	const field = (id) => form.querySelector('#wpforms-6-field_' + id + '-container') || form.querySelector('[id$="-field_' + id + '-container"]');

	const categoryMeta = {
		'veicoli': ['Veicoli','Auto, moto e mezzi','car'],
		'veicolo o mobilità': ['Veicoli','Auto, moto e mezzi','car'],
		'case e attività': ['Abitazioni e attività','Case, condomini, negozi, uffici','home'],
		'abitazioni e attività': ['Abitazioni e attività','Case, condomini, negozi, uffici','home'],
		'furti, effrazioni e sicurezza': ['Abitazioni e attività','Case, negozi, uffici e sicurezza','home'],
		'sicurezza o comportamento sospetto': ['Abitazioni e attività','Case, negozi, uffici e sicurezza','home'],
		'pericoli': ['Pericoli','Strade, ostacoli e rischi','warning'],
		'pericolo territoriale': ['Pericoli','Strade, ostacoli e rischi','warning'],
		'spazi pubblici': ['Spazi pubblici','Rifiuti, degrado, buche…','public'],
		'degrado urbano': ['Spazi pubblici','Rifiuti, degrado e arredo urbano','public'],
		'problema o disservizio di quartiere': ['Spazi pubblici','Disservizi e problemi di quartiere','public'],
		'animali': ['Animali','Smarriti, ritrovati, avvistati…','animal'],
		'animale': ['Animali','Smarriti, ritrovati, avvistati…','animal'],
		'oggetti e documenti': ['Oggetti e documenti','Portafogli, chiavi, documenti…','object'],
		'oggetto smarrito o ritrovato': ['Oggetti e documenti','Portafogli, chiavi, documenti…','object'],
		'altro': ['Altro','Situazioni non comprese sopra','more']
	};

	const icons = {
		car:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h16l-1.5-5h-13L4 13Zm1 0v5m14-5v5M7 18h10M7 8l1.2-3h7.6L17 8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
		home:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-7 9 7M6 9v11h12V9M9 20v-6h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
		warning:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 20h18L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8"/></svg>',
		public:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9l8-5 8 5v11M2 20h20M8 13h8M9 17h6" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>',
		animal:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12c-2 0-3.5 1.4-3.5 3.2C4.5 18 7.6 20 12 20s7.5-2 7.5-4.8C19.5 13.4 18 12 16 12m-8 0c.7-2.1 2.1-3.3 4-3.3s3.3 1.2 4 3.3M6.5 8.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM12 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
		object:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v12H7zM9 7V5h6v2M9 11h6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
		more:'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/></svg>'
	};

	const categoryField = field(2);
	if (categoryField) {
		categoryField.classList.add('badaround-event-category');
		categoryField.querySelectorAll('.wpforms-field-label-inline').forEach((label) => {
			if (label.dataset.baEnhanced === '1') return;
			const raw = label.textContent.trim();
			const meta = categoryMeta[raw.toLowerCase()] || [raw,'', 'more'];
			label.textContent = '';
			const icon = document.createElement('span');
			icon.className = 'ba-choice-icon';
			icon.setAttribute('aria-hidden','true');
			icon.innerHTML = icons[meta[2]] || icons.more;
			const title = document.createElement('span');
			title.className = 'ba-choice-title';
			title.textContent = meta[0];
			const desc = document.createElement('span');
			desc.className = 'ba-choice-desc';
			desc.textContent = meta[1];
			label.append(icon,title,desc);
			label.dataset.baEnhanced = '1';
			label.dataset.baTitle = meta[0];
			label.dataset.baDesc = meta[1];
		});
	}

	[3,4,5,6,7,8,9,10,11].forEach((id) => {
		const el = field(id);
		if (el) el.classList.add(id === 11 ? 'ba-relationship-field' : 'ba-drilldown-field');
	});

	const choiceEmoji = (text) => {
		const t=text.toLowerCase();
		if(t.includes('furto')||t.includes('rubat')) return '🚨';
		if(t.includes('danno')||t.includes('vandal')) return '💥';
		if(t.includes('incidente')||t.includes('fuga')) return '🏃';
		if(t.includes('abbandon')) return '🚧';
		if(t.includes('sospett')) return '👀';
		if(t.includes('albero')||t.includes('ramo')) return '🌳';
		if(t.includes('rifiut')||t.includes('discarica')) return '🗑️';
		if(t.includes('illumin')) return '💡';
		if(t.includes('buche')||t.includes('strada')) return '🛣️';
		if(t.includes('smarrit')) return '🔎';
		if(t.includes('ritrovat')) return '✓';
		if(t.includes('ferito')) return '🩹';
		if(t.includes('document')) return '🪪';
		if(t.includes('chiavi')) return '🔑';
		if(t.includes('telefono')||t.includes('computer')) return '📱';
		if(t.includes('altro')) return '＋';
		return '•';
	};

	form.querySelectorAll('.ba-drilldown-field .wpforms-field-label-inline').forEach((label)=>{
		if(label.querySelector('.ba-drilldown-icon'))return;
		const icon=document.createElement('span');
		icon.className='ba-drilldown-icon';
		icon.setAttribute('aria-hidden','true');
		icon.textContent=choiceEmoji(label.textContent.trim());
		label.prepend(icon);
	});

	const selectedSummary=document.createElement('div');
	selectedSummary.className='ba-selected-category';
	selectedSummary.hidden=true;
	if(categoryField) categoryField.after(selectedSummary);

	const categoryRadios=categoryField ? [...categoryField.querySelectorAll('input[type="radio"]')] : [];

	/* WPForms conditional logic may call scroll/focus and also changes the
	 * document height when fields are revealed. Preserve the clicked card's
	 * visual position instead of merely restoring the old scrollY. */
	let allowPageNavigationScroll=false;
	let selectionAnchor=null;

	const captureSelectionAnchor=(event)=>{
		if(event.target.closest?.('.wpforms-page-next,.wpforms-page-prev')) return;
		const choice=event.target.closest?.(
			'.wpforms-field-label-inline, input[type="radio"], input[type="checkbox"]'
		);
		if(!choice) return;

		const container=choice.closest('.wpforms-field');
		const anchor=choice.closest('li') || container || choice;
		selectionAnchor={
			node:anchor,
			containerId:container?.id || '',
			top:anchor.getBoundingClientRect().top
		};
	};

	const restoreSelectionAnchor=()=>{
		if(allowPageNavigationScroll || !selectionAnchor) return;

		let anchor=selectionAnchor.node;
		if(!anchor?.isConnected && selectionAnchor.containerId){
			anchor=document.getElementById(selectionAnchor.containerId);
		}
		if(!anchor?.isConnected) return;

		const currentTop=anchor.getBoundingClientRect().top;
		const delta=currentTop-selectionAnchor.top;
		if(Math.abs(delta)>1){
			window.scrollBy({top:delta,left:0,behavior:'auto'});
		}
	};

	const preserveSelectionScroll=()=>{
		requestAnimationFrame(restoreSelectionAnchor);
		setTimeout(restoreSelectionAnchor,0);
		setTimeout(restoreSelectionAnchor,40);
		setTimeout(restoreSelectionAnchor,120);
		setTimeout(restoreSelectionAnchor,260);
		setTimeout(()=>{ selectionAnchor=null; },340);
	};

	form.addEventListener('pointerdown',captureSelectionAnchor,true);
	form.addEventListener('click',captureSelectionAnchor,true);
	form.addEventListener('change',(event)=>{
		if(!event.target.matches?.('input[type="radio"],input[type="checkbox"]')) return;
		preserveSelectionScroll();
	},true);
	const syncSelectedCategory=()=>{
		const selected=categoryRadios.find(r=>r.checked);
		if(!selected){ selectedSummary.hidden=true; return; }
		const label=selected.nextElementSibling;
		if(!label){ selectedSummary.hidden=true; return; }
		const title=label.dataset.baTitle || label.textContent.trim();
		const desc=label.dataset.baDesc || '';
		const icon=label.querySelector('.ba-choice-icon')?.innerHTML || icons.more;
		selectedSummary.innerHTML='<div class="ba-selected-category__main"><span class="ba-selected-category__icon" aria-hidden="true">'+icon+'</span><span class="ba-selected-category__text"><strong>'+title+'</strong><small>'+desc+'</small></span></div><button type="button">Cambia</button>';
		selectedSummary.hidden=false;
		selectedSummary.querySelector('button')?.addEventListener('click',()=>{
			categoryField?.scrollIntoView({behavior:'smooth',block:'center'});
		});
	};
	categoryRadios.forEach(r=>r.addEventListener('change',syncSelectedCategory));
	syncSelectedCategory();

	/* Step 2: location and privacy emphasis */
	const locationPrimary=field(31);
	if(locationPrimary){
		locationPrimary.classList.add('ba-location-primary','ba-location-first');
		const helper=document.createElement('div');
		helper.className='ba-location-helper';
		helper.innerHTML='<span aria-hidden="true">🔒</span><div><strong>Posizione protetta.</strong> Il civico e la posizione precisa restano riservati; al pubblico mostreremo solo l’area prevista dalle tue impostazioni.</div>';
		locationPrimary.append(helper);
	}
	[29,32,33].forEach(id=>field(id)?.classList.add('ba-location-secondary'));
	field(34)?.classList.add('ba-danger-field');

	/* Step 3+ visual grouping without changing WPForms logic */
	const classFields=(ids,classes)=>ids.forEach(id=>field(id)?.classList.add(...classes));
	classFields([37,38,39,40,41,42,43,44],['ba-form-section']);
	classFields([45,46,47,48],['ba-form-section']);
	classFields([49,50,51],['ba-form-section']);
	classFields([87,88,89,90,91],['ba-form-section','ba-form-section--sensitive']);
	classFields([55,56,57,58,59,60,61,62],['ba-form-section']);
	classFields([63,64],['ba-form-section','ba-form-section--media']);
	classFields([65,66,68,69,70],['ba-form-section']);
	classFields([73,74,75,76,77,78,80,81,82,83,84,85,86],['ba-form-section','ba-form-section--identity']);

	const stepLabel=document.querySelector('[data-ba-report-step-label]');
	const title=document.querySelector('[data-ba-report-title]');
	const eyebrow=document.querySelector('[data-ba-report-eyebrow]');
	const copy=document.querySelector('[data-ba-report-copy]');
	const progress=[...document.querySelectorAll('[data-ba-report-progress]')];

	const activeWpPageIndex=()=>{
		const pages=[...form.querySelectorAll('.wpforms-page')];
		const visible=pages.findIndex(p=>getComputedStyle(p).display!=='none');
		return visible>=0?visible:0;
	};
	const macroStep=(wpIndex)=>wpIndex===0?1:(wpIndex===1?2:3);
	const stepCopy={
		1:['Cosa','Cosa riguarda la segnalazione?',"Tocca il soggetto o l'ambiente coinvolto. Mostreremo solo le domande necessarie."],
		2:['Dove','Dove è successo?','Parti dalla mappa: cerca il punto oppure sposta il pin. Poi ti chiederemo quando è successo.'],
		3:['Dettagli e invio','Completa la segnalazione','Aggiungi solo le informazioni utili. Foto e dettagli aiutano la community e la moderazione.']
	};
	let lastWpPageIndex=activeWpPageIndex();
	const syncStep=(shouldScroll=false)=>{
		const currentWpPageIndex=activeWpPageIndex();
		const step=macroStep(currentWpPageIndex);
		if(stepLabel)stepLabel.textContent='Passo '+step+' di 3';
		if(eyebrow)eyebrow.textContent=stepCopy[step][0];
		if(title)title.textContent=stepCopy[step][1];
		if(copy)copy.textContent=stepCopy[step][2];
		progress.forEach((bar,index)=>{
			bar.classList.toggle('is-active',index===step-1);
			bar.classList.toggle('is-complete',index<step-1);
		});
		const next=form.querySelector('.wpforms-page:not([style*="display: none"]) .wpforms-page-next');
		if(next){
			next.textContent=step===1?'Continua: Dove e quando →':step===2?'Continua: Dettagli e invio →':'Continua →';
		}
		if(shouldScroll && currentWpPageIndex!==lastWpPageIndex){
			document.querySelector('.ba-report-stage')?.scrollIntoView({behavior:'smooth',block:'start'});
		}
		lastWpPageIndex=currentWpPageIndex;
	};

	const observer=new MutationObserver(()=>requestAnimationFrame(()=>syncStep(false)));
	observer.observe(form,{attributes:true,subtree:true,attributeFilter:['style','class']});
	form.addEventListener('click',(e)=>{
		if(e.target.closest('.wpforms-page-next,.wpforms-page-prev')){
			allowPageNavigationScroll=true;
			setTimeout(()=>{
				syncStep(true);
				allowPageNavigationScroll=false;
			},120);
		}
	});
	syncStep(false);

	/* Save topbar delegates to WPForms Save & Resume */
	document.querySelector('[data-ba-report-save]')?.addEventListener('click',()=>{
		const save=[...form.querySelectorAll('a,button')].find(el=>{
			const text=(el.textContent||'').toLowerCase();
			return el.classList.contains('wpforms-save-resume-button')||text.includes('salva e continua')||text.includes('salva e riprendi');
		});
		if(save) save.click();
	});

	/* Keep relationship hidden until a macro-category exists */
	const relationship=field(11);
	const syncRelationship=()=>{
		if(!relationship||!categoryRadios.length)return;
		const selected=categoryRadios.some(r=>r.checked);
		relationship.hidden=!selected;
		relationship.setAttribute('aria-hidden',selected?'false':'true');
	};
	categoryRadios.forEach(r=>r.addEventListener('change',syncRelationship));
	syncRelationship();
})();
