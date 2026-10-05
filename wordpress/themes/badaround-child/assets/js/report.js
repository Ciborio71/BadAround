(() => {
	'use strict';

	const root = document.querySelector('#wpforms-6');
	if (!root) return;

	const form = root.querySelector('form') || root;
	const field = (id) => root.querySelector('#wpforms-6-field_' + id + '-container') || root.querySelector('[id$="-field_' + id + '-container"]');

	const categoryMeta = {
		'veicoli': { title:'Veicoli', desc:'Auto, moto e altri mezzi', icon:'vehicle', color:'#1688e8' },
		'case e attività': { title:'Case e attività', desc:'Abitazioni, negozi e uffici', icon:'property', color:'#9c3152' },
		'pericoli': { title:'Pericoli', desc:'Rischi e situazioni pericolose', icon:'hazard', color:'#e36b21' },
		'spazi pubblici': { title:'Spazi pubblici', desc:'Strade, aree pubbliche e decoro', icon:'public', color:'#087f8c' },
		'animali': { title:'Animali', desc:'Smarrimenti e segnalazioni', icon:'animal', color:'#7e3db6' },
		'oggetti e documenti': { title:'Oggetti e documenti', desc:'Oggetti smarriti o ritrovati', icon:'object', color:'#a26f14' }
	};

	const icons = {
		vehicle:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 14h15l-1.8-5.2a2 2 0 0 0-1.9-1.3H8.2a2 2 0 0 0-1.9 1.3L4.5 14Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 14v4m14-4v4M7.5 18h9M7.2 12h.01M16.8 12h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
		property:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-7 9 7M6 9.5V20h12V9.5M9 20v-6h6v6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
		hazard:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3.5 20h17L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
		public:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9l8-5 8 5v11M2 20h20M8 13h8M9 17h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
		animal:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12c-2 0-3.5 1.4-3.5 3.2C4.5 18 7.6 20 12 20s7.5-2 7.5-4.8C19.5 13.4 18 12 16 12m-8 0c.7-2.1 2.1-3.3 4-3.3s3.3 1.2 4 3.3M6.5 8.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM12 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
		object:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7h8l2 3v9H6v-9l2-3Zm1 0V5h6v2M9 12h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>'
	};

	const stripLeadingEmoji = (value) => value.replace(/^[^\p{L}\p{N}]+/u,'').trim();

	const categoryField = field(2);
	const categoryRadios = categoryField ? [...categoryField.querySelectorAll('input[type="radio"]')] : [];

	if (categoryField) {
		categoryField.classList.add('badaround-event-category');
		categoryField.querySelectorAll('.wpforms-field-label-inline').forEach((label) => {
			if (label.dataset.baEnhanced === '1') return;
			const raw = stripLeadingEmoji(label.textContent.trim());
			const meta = categoryMeta[raw.toLowerCase()];
			if (!meta) return;

			label.textContent = '';
			label.style.setProperty('--ba-category', meta.color);

			const icon = document.createElement('span');
			icon.className = 'ba-choice-icon';
			icon.setAttribute('aria-hidden','true');
			icon.innerHTML = icons[meta.icon] || '';

			const title = document.createElement('span');
			title.className = 'ba-choice-title';
			title.textContent = meta.title;

			const desc = document.createElement('span');
			desc.className = 'ba-choice-desc';
			desc.textContent = meta.desc;

			const check = document.createElement('span');
			check.className = 'ba-choice-check';
			check.setAttribute('aria-hidden','true');
			check.textContent = '✓';

			label.append(icon,title,desc,check);
			label.dataset.baEnhanced = '1';
			label.dataset.baTitle = meta.title;
			label.dataset.baDesc = meta.desc;
			label.dataset.baColor = meta.color;
		});
		const order = ['Veicoli','Case e attività','Pericoli','Spazi pubblici','Animali','Oggetti e documenti'];
		const list = categoryField.querySelector('ul');
		if (list) {
			const items = [...list.children];
			items.sort((a,b) => {
				const aTitle = a.querySelector('.wpforms-field-label-inline')?.dataset.baTitle || '';
				const bTitle = b.querySelector('.wpforms-field-label-inline')?.dataset.baTitle || '';
				return order.indexOf(aTitle) - order.indexOf(bTitle);
			});
			items.forEach((item) => list.appendChild(item));
		}
	}

	[3,4,5,6,7,8].forEach((id) => field(id)?.classList.add('ba-drilldown-field'));
	field(11)?.classList.add('ba-relationship-field');

	const choiceGlyph = (text) => {
		const t = stripLeadingEmoji(text).toLowerCase();
		if (t.includes('rubat') || t.includes('furto')) return '↗';
		if (t.includes('danno') || t.includes('vandal')) return '✦';
		if (t.includes('fuga')) return '→';
		if (t.includes('sospett') || t.includes('avvist')) return '◉';
		if (t.includes('albero') || t.includes('ramo')) return '⌁';
		if (t.includes('rifiut')) return '▤';
		if (t.includes('illumin')) return '✧';
		if (t.includes('strada') || t.includes('buca')) return '═';
		if (t.includes('smarrit')) return '⌕';
		if (t.includes('ritrovat') || t.includes('trovato')) return '✓';
		if (t.includes('ferito')) return '+';
		if (t.includes('document')) return '▣';
		if (t.includes('chiavi')) return '⌘';
		if (t.includes('elettron')) return '▥';
		if (t.includes('altro')) return '…';
		return '•';
	};

	root.querySelectorAll('.ba-drilldown-field .wpforms-field-label-inline').forEach((label) => {
		if (label.dataset.baDrilldownEnhanced === '1') return;
		const displayText = stripLeadingEmoji(label.textContent.trim());
		label.textContent = '';

		const icon = document.createElement('span');
		icon.className = 'ba-drilldown-icon';
		icon.setAttribute('aria-hidden','true');
		icon.textContent = choiceGlyph(displayText);

		const text = document.createElement('span');
		text.className = 'ba-drilldown-text';
		text.textContent = displayText;

		const radio = document.createElement('span');
		radio.className = 'ba-drilldown-radio';
		radio.setAttribute('aria-hidden','true');

		label.append(icon,text,radio);
		label.dataset.baDrilldownEnhanced = '1';
	});

	/* Visual grouping only: no fields are moved and no WPForms logic is altered. */
	const classFields = (ids, classes) => ids.forEach((id) => field(id)?.classList.add(...classes));
	classFields([37,38,39,40,41,42,43,44],['ba-form-section']);
	classFields([45,46,47,48],['ba-form-section']);
	classFields([49,50,51],['ba-form-section']);
	classFields([87,88,89,91],['ba-form-section','ba-form-section--sensitive']);
	classFields([55,56,57,59,60,61,62],['ba-form-section']);
	classFields([63,64],['ba-form-section','ba-form-section--media']);
	classFields([65,66,68,69,70],['ba-form-section']);
	classFields([73,74,75,76,77,78,80,81,82,83,84,85,86],['ba-form-section','ba-form-section--identity']);

	/* Step 2 location emphasis. */
	const locationPrimary = field(31);
	if (locationPrimary) {
		locationPrimary.classList.add('ba-location-primary','ba-location-first');
		if (!locationPrimary.querySelector('.ba-location-helper')) {
			const helper = document.createElement('div');
			helper.className = 'ba-location-helper';
			helper.innerHTML = '<span aria-hidden="true">🔒</span><div><strong>Posizione protetta.</strong> Il civico e la posizione precisa restano riservati; al pubblico viene mostrato solo il livello previsto dalla segnalazione.</div>';
			locationPrimary.append(helper);
		}
	}
	[29,32,33].forEach((id) => field(id)?.classList.add('ba-location-secondary'));
	field(34)?.classList.add('ba-danger-field');

	/* Keep the reporter relationship question hidden until a macro-category exists. */
	const relationship = field(11);
	const syncRelationship = () => {
		if (!relationship || !categoryRadios.length) return;
		const selected = categoryRadios.some((radio) => radio.checked);
		relationship.hidden = !selected;
		relationship.setAttribute('aria-hidden', selected ? 'false' : 'true');
	};
	categoryRadios.forEach((radio) => radio.addEventListener('change',syncRelationship));
	syncRelationship();

	/* Category context chip for later steps. */
	const context = document.querySelector('[data-ba-report-context]');
	const selectedCategoryMeta = () => {
		const selected = categoryRadios.find((radio) => radio.checked);
		if (!selected) return null;
		const label = selected.nextElementSibling;
		if (!label) return null;
		return {
			title: label.dataset.baTitle || stripLeadingEmoji(label.textContent.trim()),
			color: label.dataset.baColor || '#0798a8'
		};
	};
	const syncContext = (step) => {
		if (!context) return;
		const meta = selectedCategoryMeta();
		if (!meta || step === 1) {
			context.hidden = true;
			context.textContent = '';
			return;
		}
		context.hidden = false;
		context.style.setProperty('--ba-report-context-color',meta.color);
		context.innerHTML = '<span class="ba-report-context__dot" aria-hidden="true"></span><span>' + meta.title + '</span>';
	};
	categoryRadios.forEach((radio) => radio.addEventListener('change',() => syncContext(activePageIndex()+1)));

	/* Preserve click position while conditional fields open/close. */
	let allowPageNavigationScroll = false;
	let selectionAnchor = null;
	const captureSelectionAnchor = (event) => {
		if (event.target.closest?.('.wpforms-page-next,.wpforms-page-prev')) return;
		const choice = event.target.closest?.('.wpforms-field-label-inline,input[type="radio"],input[type="checkbox"]');
		if (!choice) return;
		const container = choice.closest('.wpforms-field');
		const anchor = choice.closest('li') || container || choice;
		selectionAnchor = {
			node:anchor,
			containerId:container?.id || '',
			top:anchor.getBoundingClientRect().top
		};
	};
	const restoreSelectionAnchor = () => {
		if (allowPageNavigationScroll || !selectionAnchor) return;
		let anchor = selectionAnchor.node;
		if (!anchor?.isConnected && selectionAnchor.containerId) anchor = document.getElementById(selectionAnchor.containerId);
		if (!anchor?.isConnected) return;
		const delta = anchor.getBoundingClientRect().top - selectionAnchor.top;
		if (Math.abs(delta) > 1) window.scrollBy({top:delta,left:0,behavior:'auto'});
	};
	const preserveSelectionScroll = () => {
		const started = performance.now();
		const keep = (now) => {
			if (allowPageNavigationScroll || !selectionAnchor) return;
			restoreSelectionAnchor();
			if (now - started < 650) requestAnimationFrame(keep);
			else selectionAnchor = null;
		};
		requestAnimationFrame(keep);
	};
	root.addEventListener('pointerdown',captureSelectionAnchor,true);
	root.addEventListener('click',captureSelectionAnchor,true);
	root.addEventListener('change',(event) => {
		if (!event.target.matches?.('input[type="radio"],input[type="checkbox"]')) return;
		preserveSelectionScroll();
	},true);

	/* Five real WPForms pages = five visible BadAround steps. */
	const stepItems = [...document.querySelectorAll('[data-ba-report-step]')];
	const eyebrow = document.querySelector('[data-ba-report-eyebrow]');
	const title = document.querySelector('[data-ba-report-title]');
	const copy = document.querySelector('[data-ba-report-copy]');

	const stepCopy = {
		1:['SEGNALA UN EVENTO','Cosa Vuoi Segnalare?','<p>Un <strong>furto</strong>, un <strong>danno</strong>, un <strong>comportamento sospetto</strong>, uno <strong>smarrimento</strong>, un <strong>pericolo</strong> oppure stai <strong>cercando testimoni</strong>?</p><p>Ti guideremo noi e ti mostreremo solo le domande necessarie per segnalare l\'evento alla Community di BadAround!</p>'],
		2:['PASSAGGIO 2 DI 5','Dove e quando è successo?','Indica il luogo e il momento dell’evento. La posizione pubblica seguirà sempre le regole di privacy BadAround.'],
		3:['PASSAGGIO 3 DI 5','Aggiungi i dettagli','Vedrai soltanto le domande pertinenti alla categoria e al tipo di evento che hai scelto.'],
		4:['PASSAGGIO 4 DI 5','Racconta e documenta','Descrivi i fatti in modo chiaro e aggiungi eventuali foto o informazioni utili alla verifica.'],
		5:['PASSAGGIO 5 DI 5','Controlla e invia','Verifica i dati, scegli come apparire pubblicamente e completa le conferme prima dell’invio.']
	};

	const activePageIndex = () => {
		const pages = [...root.querySelectorAll('.wpforms-page')];
		const visible = pages.findIndex((page) => getComputedStyle(page).display !== 'none');
		return visible >= 0 ? visible : 0;
	};

	let lastPage = activePageIndex();
	const syncStep = (shouldScroll = false) => {
		const pageIndex = activePageIndex();
		const step = Math.min(5,pageIndex + 1);
		const content = stepCopy[step];

		if (eyebrow) eyebrow.textContent = content[0];
		if (title) title.textContent = content[1];
		if (copy) {
			if (step === 1) copy.innerHTML = content[2];
			else copy.textContent = content[2];
		}

		stepItems.forEach((item) => {
			const itemStep = Number(item.dataset.baReportStep || 0);
			item.classList.toggle('is-active',itemStep === step);
			item.classList.toggle('is-complete',itemStep < step);
			if (itemStep === step) item.setAttribute('aria-current','step');
			else item.removeAttribute('aria-current');
		});

		syncContext(step);
		root.classList.toggle('is-final-step', step === 5);

		const visiblePage = root.querySelector('.wpforms-page:not([style*="display: none"])');
		const next = visiblePage?.querySelector('.wpforms-page-next');
		if (next) {
			const labels = {
				1:'Continua: luogo e momento →',
				2:'Continua: dettagli →',
				3:'Continua: contenuti →',
				4:'Continua: verifica →'
			};
			next.textContent = labels[step] || 'Continua →';
		}

		if (shouldScroll && pageIndex !== lastPage) {
			document.querySelector('.ba-report-workspace')?.scrollIntoView({behavior:'smooth',block:'start'});
		}
		lastPage = pageIndex;
	};

	const observer = new MutationObserver(() => requestAnimationFrame(() => syncStep(false)));
	observer.observe(root,{attributes:true,subtree:true,attributeFilter:['style','class']});

	root.addEventListener('click',(event) => {
		if (!event.target.closest('.wpforms-page-next,.wpforms-page-prev')) return;
		allowPageNavigationScroll = true;
		setTimeout(() => {
			syncStep(true);
			allowPageNavigationScroll = false;
		},120);
	});

	/* Top save action delegates to WPForms Save & Resume. */
	document.querySelectorAll('[data-ba-report-save]').forEach((button) => {
		button.addEventListener('click',() => {
			const save = [...root.querySelectorAll('a,button')].find((element) => {
				const text = (element.textContent || '').toLowerCase();
				return element.classList.contains('wpforms-save-resume-button') || text.includes('salva e continua') || text.includes('salva e riprendi');
			});
			if (save) save.click();
		});
	});

	syncStep(false);
})();
