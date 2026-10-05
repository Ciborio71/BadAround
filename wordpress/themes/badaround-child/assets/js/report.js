(() => {
	'use strict';

	const root = document.querySelector('#wpforms-6');
	if (!root) return;

	const form = root.querySelector('form') || root;
	const field = (id) => root.querySelector('#wpforms-6-field_' + id + '-container') || root.querySelector('[id$="-field_' + id + '-container"]');

	/* Legacy residue: field 9 is not part of the approved reporting flow.
	 * Remove every rendered instance to avoid duplicate IDs and accidental submission. */
	root.querySelectorAll('[data-field-id="9"]').forEach((container) => container.remove());

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

	root.querySelectorAll('.ba-relationship-field .wpforms-field-label-inline').forEach((label) => {
		if (label.dataset.baRelationshipEnhanced === '1') return;
		const displayText = stripLeadingEmoji(label.textContent.trim());
		label.textContent = '';

		const icon = document.createElement('span');
		icon.className = 'ba-drilldown-icon';
		icon.setAttribute('aria-hidden','true');
		icon.textContent = displayText.toLowerCase().includes('testimon') ? '◉' : '•';

		const text = document.createElement('span');
		text.className = 'ba-drilldown-text';
		text.textContent = displayText;

		const radio = document.createElement('span');
		radio.className = 'ba-drilldown-radio';
		radio.setAttribute('aria-hidden','true');

		label.append(icon,text,radio);
		label.dataset.baRelationshipEnhanced = '1';
	});

	const detailChoiceIcon = (text) => {
		const t = stripLeadingEmoji(text).toLowerCase();
		if (t.includes('automobile')) return '🚗';
		if (t.includes('motocicletta')) return '🏍';
		if (t.includes('scooter') || t.includes('ciclomotore')) return '🛵';
		if (t.includes('furgone')) return '🚐';
		if (t.includes('camion') || t.includes('mezzo pesante')) return '🚚';
		if (t.includes('camper')) return '🚐';
		if (t.includes('autobus')) return '🚌';
		if (t.includes('bicicletta')) return '🚲';
		if (t.includes('monopattino')) return '🛴';
		if (t.includes('agricolo') || t.includes('da lavoro')) return '⚙';
		if (t.includes('cane')) return '🐕';
		if (t.includes('gatto')) return '🐈';
		if (t.includes('uccello')) return '◒';
		if (t.includes('coniglio')) return '◉';
		if (t.includes('porta') || t.includes('finestra') || t.includes('serratura')) return '⌂';
		if (t.includes('denaro')) return '€';
		if (t.includes('gioiell')) return '◇';
		if (t.includes('elettronic')) return '▥';
		if (t.includes('document')) return '▣';
		if (t.includes('chiav')) return '⌘';
		if (t.includes('testimon')) return '';
		if (t.includes('coinvolt')) return '';
		if (t.includes('sì') || t.startsWith('si ')) return '';
		if (t.includes('no')) return '';
		if (t.includes('altro') || t.includes('non lo so') || t.includes('non sono')) return '';
		return '';
	};

	root.querySelectorAll('.wpforms-page-3 .wpforms-field-radio, .wpforms-page-3 .wpforms-field-checkbox, .wpforms-page-4 .wpforms-field-radio, .wpforms-page-4 .wpforms-field-checkbox, .wpforms-page-5 .wpforms-field-radio, .wpforms-page-5 .wpforms-field-checkbox').forEach((container) => {
		container.classList.add('ba-detail-choice-field');
		container.querySelectorAll('.wpforms-field-label-inline').forEach((label) => {
			if (label.dataset.baDetailEnhanced === '1') return;
			const input = label.previousElementSibling;
			const displayText = stripLeadingEmoji(label.textContent.trim());
			label.textContent = '';

			const iconValue = detailChoiceIcon(displayText);
			const icon = document.createElement('span');
			icon.className = 'ba-detail-choice-icon';
			icon.setAttribute('aria-hidden','true');
			icon.textContent = iconValue;
			if (!iconValue) icon.hidden = true;

			const text = document.createElement('span');
			text.className = 'ba-detail-choice-text';
			text.textContent = displayText;

			const indicator = document.createElement('span');
			indicator.className = 'ba-detail-choice-indicator' + (input?.type === 'checkbox' ? ' is-checkbox' : '');
			indicator.setAttribute('aria-hidden','true');

			label.append(icon,text,indicator);
			label.dataset.baDetailEnhanced = '1';
		});
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

	/* Step 4–5 semantic cleanup and progressive disclosure. */
	const page4 = root.querySelector('.wpforms-page-4');
	const photoUpload = field(63);
	const mediaAvailability = field(64);
	const rewardFields = [65,66,68,69,70].map(field).filter(Boolean);
	const contentRightsConfirmation = field(82);

	if (page4 && photoUpload && mediaAvailability && mediaAvailability.parentElement === page4) {
		page4.insertBefore(mediaAvailability,photoUpload);
	}

	const mediaDescription = mediaAvailability?.querySelector('.wpforms-field-description');
	if (mediaDescription) {
		mediaDescription.textContent = 'Se disponi di fotografie potrai caricarle qui. Per eventuali video, BadAround ti indicherà successivamente come trasmetterli.';
	}

	const setContextVisibility = (container,visible,{clear=false}={}) => {
		if (!container) return;
		container.classList.toggle('ba-context-hidden',!visible);
		container.setAttribute('aria-hidden',visible ? 'false' : 'true');
		container.querySelectorAll('input,select,textarea').forEach((control) => {
			if (!visible) {
				if (control.required) control.dataset.baWasRequired = '1';
				control.required = false;
				if (clear) {
					if (control.matches('input[type="radio"],input[type="checkbox"]')) control.checked = false;
					else if (control.tagName === 'SELECT') control.selectedIndex = 0;
					else if (!control.matches('input[type="file"]')) control.value = '';
				}
			} else if (control.dataset.baWasRequired === '1') {
				control.required = true;
				delete control.dataset.baWasRequired;
			}
		});
	};

	const selectedText = (container) => container?.querySelector('input[type="radio"]:checked')?.value || '';
	const rewardIsRelevant = () => {
		const vehicleEvent = selectedText(field(3)).toLowerCase();
		const animalEvent = selectedText(field(6)).toLowerCase();
		const objectStatus = selectedText(field(51)).toLowerCase();
		return vehicleEvent.includes('veicolo rubato')
			|| animalEvent.includes('smarrito il mio animale')
			|| objectStatus.includes('smarrito l’oggetto');
	};
	const syncRewardSection = () => {
		const visible = rewardIsRelevant();
		const rewardStatus = field(65);
		const noReward = rewardStatus?.querySelector('input[type="radio"][value="No"]');

		if (!visible && noReward && !noReward.checked) {
			noReward.checked = true;
			noReward.dataset.baAutoContextValue = '1';
			noReward.dispatchEvent(new Event('input',{bubbles:true}));
			noReward.dispatchEvent(new Event('change',{bubbles:true}));
		} else if (visible && noReward?.dataset.baAutoContextValue === '1') {
			noReward.checked = false;
			delete noReward.dataset.baAutoContextValue;
			noReward.dispatchEvent(new Event('input',{bubbles:true}));
			noReward.dispatchEvent(new Event('change',{bubbles:true}));
		}

		rewardFields.forEach((container) => setContextVisibility(container,visible,{clear:false}));
	};
	[3,6,51].forEach((id) => field(id)?.querySelectorAll('input[type="radio"]').forEach((radio) => {
		radio.addEventListener('change',() => requestAnimationFrame(syncRewardSection));
	}));
	syncRewardSection();

	const hasDirectMedia = () => {
		const value = selectedText(mediaAvailability).toLowerCase();
		return value.startsWith('sì, dispongo di immagini o video');
	};
	const syncPhotoUpload = () => setContextVisibility(photoUpload,hasDirectMedia());
	mediaAvailability?.querySelectorAll('input[type="radio"]').forEach((radio) => {
		radio.addEventListener('change',() => requestAnimationFrame(syncPhotoUpload));
	});
	syncPhotoUpload();

	const contentRightsLabel = contentRightsConfirmation?.querySelector('.wpforms-field-label-inline');
	if (contentRightsLabel) {
		contentRightsLabel.textContent = 'Se ho allegato fotografie o altri contenuti, dichiaro di avere il diritto di caricarli e condividerli con BadAround.';
	}
	const syncContentRightsConfirmation = () => {
		setContextVisibility(contentRightsConfirmation,true,{clear:false});
	};
	syncContentRightsConfirmation();

	const publicContactField = field(78);
	const publicContactLegend = publicContactField?.querySelector('legend.wpforms-field-label');
	if (publicContactLegend?.firstChild) {
		publicContactLegend.firstChild.textContent = 'Vuoi ricevere messaggi dalla community relativi alla segnalazione? ';
	}
	const publicContactDescription = publicContactField?.querySelector('.wpforms-field-description');
	if (publicContactDescription) {
		publicContactDescription.textContent = 'Email e telefono non saranno mostrati pubblicamente. Questa scelta riguarda i messaggi della community; BadAround potrà comunque inviarti comunicazioni operative sulla segnalazione.';
	}

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

	/* Step 2 exact-date progressive flow:
	 * date first, then time knowledge, then WPForms reveals the exact time. */
	const eventTiming = field(16);
	const eventDate = field(17);
	const timeKnowledge = field(18);
	const eventDateInput = eventDate?.querySelector('input');
	const exactDateRadio = eventTiming?.querySelector('input[type="radio"][value="In una data precisa"]');

	const syncExactDateFlow = () => {
		if (!timeKnowledge) return;
		const exactDateSelected = !!exactDateRadio?.checked;
		const hasDate = !!eventDateInput?.value.trim();
		const revealTimeKnowledge = exactDateSelected && hasDate;
		timeKnowledge.classList.toggle('ba-progressive-hidden', !revealTimeKnowledge);
		timeKnowledge.setAttribute('aria-hidden', revealTimeKnowledge ? 'false' : 'true');
	};
	eventTiming?.querySelectorAll('input[type="radio"]').forEach((radio) => {
		radio.addEventListener('change',() => requestAnimationFrame(syncExactDateFlow));
	});
	['input','change','blur'].forEach((eventName) => {
		eventDateInput?.addEventListener(eventName,() => requestAnimationFrame(syncExactDateFlow));
	});
	syncExactDateFlow();

	const parseItalianDate = (value) => {
		const match = String(value || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
		if (!match) return null;
		const day = Number(match[1]);
		const month = Number(match[2]);
		const year = Number(match[3]);
		const date = new Date(year,month - 1,day);
		if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
		date.setHours(0,0,0,0);
		return date;
	};

	const todayLocal = () => {
		const today = new Date();
		today.setHours(0,0,0,0);
		return today;
	};

	const validateEventDate = ({focus = false} = {}) => {
		if (!eventDate || !eventDateInput) return true;
		const parsed = parseItalianDate(eventDateInput.value);
		const invalid = !!parsed && parsed.getTime() > todayLocal().getTime();

		eventDate.classList.toggle('ba-event-date-invalid',invalid);
		eventDateInput.setAttribute('aria-invalid',invalid ? 'true' : 'false');

		let error = eventDate.querySelector('.ba-event-date-error');
		if (invalid && !error) {
			error = document.createElement('div');
			error.className = 'ba-event-date-error';
			error.setAttribute('role','alert');
			error.textContent = 'La data dell’evento non può essere successiva alla data odierna.';
			eventDate.append(error);
		} else if (!invalid && error) {
			error.remove();
		}

		if (invalid && focus) {
			eventDateInput.focus({preventScroll:true});
			eventDate.scrollIntoView({behavior:'smooth',block:'center'});
		}
		return !invalid;
	};

	const constrainEventDatePicker = () => {
		if (!eventDateInput) return;
		if (eventDateInput._flatpickr) {
			eventDateInput._flatpickr.set('maxDate','today');
			return;
		}
		if (eventDateInput.type === 'date') {
			const today = new Date();
			const yyyy = today.getFullYear();
			const mm = String(today.getMonth() + 1).padStart(2,'0');
			const dd = String(today.getDate()).padStart(2,'0');
			eventDateInput.max = yyyy + '-' + mm + '-' + dd;
		}
	};
	['input','change','blur'].forEach((eventName) => {
		eventDateInput?.addEventListener(eventName,() => validateEventDate());
	});
	constrainEventDatePicker();
	setTimeout(constrainEventDatePicker,250);
	setTimeout(constrainEventDatePicker,1000);

	const exactTimeField = field(19);
	const exactTimeInput = exactTimeField?.querySelector('input');
	const timeFromField = field(20);
	const timeToField = field(22);
	const timeFromNowInput = timeFromField?.querySelector('input');
	const timeToNowInput = timeToField?.querySelector('input');

	const isEventDateToday = () => {
		const parsed = parseItalianDate(eventDateInput?.value);
		if (!parsed) return false;
		return parsed.getTime() === todayLocal().getTime();
	};

	const currentMinutes = () => {
		const now = new Date();
		return (now.getHours() * 60) + now.getMinutes();
	};

	const validateNotFutureTime = (container,input,{focus=false}={}) => {
		if (!container || !input || getComputedStyle(container).display === 'none') {
			container?.classList.remove('ba-future-time-invalid');
			container?.querySelector('.ba-future-time-error')?.remove();
			input?.removeAttribute('aria-invalid');
			return true;
		}
		const valueMinutes = minutesFromTime(input.value);
		const invalid = isEventDateToday() && valueMinutes !== null && valueMinutes > currentMinutes();

		container.classList.toggle('ba-future-time-invalid',invalid);
		input.setAttribute('aria-invalid',invalid ? 'true' : 'false');
		let error = container.querySelector('.ba-future-time-error');
		if (invalid && !error) {
			error = document.createElement('div');
			error.className = 'ba-future-time-error';
			error.setAttribute('role','alert');
			error.textContent = 'Per la data odierna non puoi indicare un orario successivo all’ora attuale.';
			container.append(error);
		} else if (!invalid && error) {
			error.remove();
		}
		if (invalid && focus) {
			input.focus({preventScroll:true});
			container.scrollIntoView({behavior:'smooth',block:'center'});
		}
		return !invalid;
	};

	const validateEventTimesAgainstNow = ({focus=false}={}) => {
		const checks = [
			[exactTimeField,exactTimeInput],
			[timeFromField,timeFromNowInput],
			[timeToField,timeToNowInput]
		];
		for (const [container,input] of checks) {
			if (!validateNotFutureTime(container,input,{focus})) return false;
		}
		return true;
	};

	['input','change','blur'].forEach((eventName) => {
		exactTimeInput?.addEventListener(eventName,() => validateEventTimesAgainstNow());
		timeFromNowInput?.addEventListener(eventName,() => validateEventTimesAgainstNow());
		timeToNowInput?.addEventListener(eventName,() => validateEventTimesAgainstNow());
		eventDateInput?.addEventListener(eventName,() => validateEventTimesAgainstNow());
	});

	/* Step 4 hygiene:
	 * - no accidental preselection on a fresh first visit;
	 * - preserve answers when navigating back/forward or resuming a saved draft;
	 * - hide damage question for a stolen vehicle, where damage cannot be verified. */
	const neutralRadioFields = [42,56,59,60,64,65].map(field).filter(Boolean);
	const damageField = field(56);
	const stolenVehicleRadio = field(3)?.querySelector('input[type="radio"][value="Veicolo rubato"]');
	const resumeSession = /resume/i.test(window.location.search) || !!root.querySelector('[name*="resume"],[data-resume]');
	let neutralDefaultsSanitized = resumeSession;

	const sanitizeNeutralRadioDefaults = () => {
		if (neutralDefaultsSanitized || resumeSession) return;
		neutralDefaultsSanitized = true;
		neutralRadioFields.forEach((container) => {
			container.querySelectorAll('input[type="radio"]:checked').forEach((input) => {
				input.checked = false;
				input.dispatchEvent(new Event('input',{bubbles:true}));
				input.dispatchEvent(new Event('change',{bubbles:true}));
			});
		});
	};
	sanitizeNeutralRadioDefaults();

	const syncDamageQuestion = () => {
		if (!damageField) return;
		const hiddenForStolenVehicle = !!stolenVehicleRadio?.checked;
		const notApplicable = damageField.querySelector('input[type="radio"][value="Non applicabile"]');

		damageField.classList.toggle('ba-context-hidden',hiddenForStolenVehicle);
		damageField.setAttribute('aria-hidden',hiddenForStolenVehicle ? 'true' : 'false');

		if (hiddenForStolenVehicle) {
			damageField.querySelectorAll('input').forEach((input) => {
				if (input.required) input.dataset.baWasRequired = '1';
				input.required = false;
			});
			if (notApplicable && !notApplicable.checked) {
				notApplicable.checked = true;
				notApplicable.dataset.baAutoContextValue = '1';
				notApplicable.dispatchEvent(new Event('input',{bubbles:true}));
				notApplicable.dispatchEvent(new Event('change',{bubbles:true}));
			}
		} else {
			if (notApplicable?.dataset.baAutoContextValue === '1') {
				notApplicable.checked = false;
				delete notApplicable.dataset.baAutoContextValue;
				notApplicable.dispatchEvent(new Event('input',{bubbles:true}));
				notApplicable.dispatchEvent(new Event('change',{bubbles:true}));
			}
			damageField.querySelectorAll('input').forEach((input) => {
				if (input.dataset.baWasRequired === '1') {
					input.required = true;
					delete input.dataset.baWasRequired;
				}
			});
		}
	};
	field(3)?.querySelectorAll('input[type="radio"]').forEach((radio) => {
		radio.addEventListener('change',() => requestAnimationFrame(syncDamageQuestion));
	});
	syncDamageQuestion();

	/* Step 2 time-range validation: ending time cannot precede starting time. */
	const timeFrom = field(20);
	const timeTo = field(22);
	const timeFromInput = timeFrom?.querySelector('input');
	const timeToInput = timeTo?.querySelector('input');

	const minutesFromTime = (value) => {
		const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})$/);
		if (!match) return null;
		const hours = Number(match[1]);
		const minutes = Number(match[2]);
		if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
		return (hours * 60) + minutes;
	};

	const validateTimeRange = ({focus = false} = {}) => {
		if (!timeFrom || !timeTo || !timeFromInput || !timeToInput) return true;
		if (getComputedStyle(timeFrom).display === 'none' || getComputedStyle(timeTo).display === 'none') {
			timeTo.classList.remove('ba-time-range-invalid');
			timeTo.querySelector('.ba-time-range-error')?.remove();
			timeToInput.removeAttribute('aria-invalid');
			return true;
		}

		const fromMinutes = minutesFromTime(timeFromInput.value);
		const toMinutes = minutesFromTime(timeToInput.value);
		const invalid = fromMinutes !== null && toMinutes !== null && toMinutes < fromMinutes;

		timeTo.classList.toggle('ba-time-range-invalid', invalid);
		timeToInput.setAttribute('aria-invalid', invalid ? 'true' : 'false');

		let error = timeTo.querySelector('.ba-time-range-error');
		if (invalid && !error) {
			error = document.createElement('div');
			error.className = 'ba-time-range-error';
			error.setAttribute('role','alert');
			error.textContent = 'L’orario finale non può essere precedente all’orario di inizio.';
			timeTo.append(error);
		} else if (!invalid && error) {
			error.remove();
		}

		if (invalid && focus) {
			timeToInput.focus({preventScroll:true});
			timeTo.scrollIntoView({behavior:'smooth',block:'center'});
		}
		return !invalid;
	};

	['input','change','blur'].forEach((eventName) => {
		timeFromInput?.addEventListener(eventName,() => validateTimeRange());
		timeToInput?.addEventListener(eventName,() => validateTimeRange());
	});

	/* Progressive disclosure: show reporter relationship only after a
	 * specific event has been selected in the currently visible branch. */
	const relationship = field(11);
	const drilldownFields = [3,4,5,6,7,8].map(field).filter(Boolean);
	const hasVisibleEventSelection = () => drilldownFields.some((container) => {
		if (container.hidden || getComputedStyle(container).display === 'none') return false;
		return !!container.querySelector('input[type="radio"]:checked');
	});
	const syncRelationship = () => {
		if (!relationship) return;
		const selected = hasVisibleEventSelection();
		relationship.hidden = !selected;
		relationship.setAttribute('aria-hidden', selected ? 'false' : 'true');
	};
	categoryRadios.forEach((radio) => radio.addEventListener('change',() => requestAnimationFrame(syncRelationship)));
	drilldownFields.forEach((container) => {
		container.querySelectorAll('input[type="radio"]').forEach((radio) => radio.addEventListener('change',syncRelationship));
	});
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
		1:['SEGNALA UN EVENTO','Cosa vuoi segnalare?','<p>Un <strong>furto</strong>, un <strong>danno</strong>, un <strong>comportamento sospetto</strong>, uno <strong>smarrimento</strong>, un <strong>pericolo</strong> oppure stai <strong>cercando testimoni</strong>?</p><p>Ti guideremo noi e ti mostreremo solo le domande necessarie per segnalare l\'evento alla community di BadAround.</p>'],
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
		for (let i = 1; i <= 5; i++) root.classList.toggle('is-step-' + i, i === step);
		if (step === 4) {
			syncDamageQuestion();
		}

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

	const observer = new MutationObserver(() => requestAnimationFrame(() => {
		syncExactDateFlow();
		validateEventDate();
		constrainEventDatePicker();
		validateEventTimesAgainstNow();
		validateTimeRange();
		syncDamageQuestion();
		syncRewardSection();
		syncPhotoUpload();
		syncContentRightsConfirmation();
		syncRelationship();
		syncStep(false);
	}));
	observer.observe(root,{attributes:true,subtree:true,attributeFilter:['style','class']});

	root.addEventListener('click',(event) => {
		const navigationButton = event.target.closest('.wpforms-page-next,.wpforms-page-prev');
		if (!navigationButton) return;
		if (navigationButton.classList.contains('wpforms-page-next') && activePageIndex() === 1) {
			if (!validateEventDate({focus:true}) || !validateEventTimesAgainstNow({focus:true}) || !validateTimeRange({focus:true})) {
				event.preventDefault();
				event.stopImmediatePropagation();
				return;
			}
		}
		allowPageNavigationScroll = true;
		setTimeout(() => {
			syncStep(true);
			allowPageNavigationScroll = false;
		},120);
	});

	/* Save & Resume is a distinct mode, not another report step. */
	const saveResumeConfirmation = root.querySelector('.wpforms-save-resume-confirmation');
	const workspace = document.querySelector('.ba-report-workspace');

	const enhanceSaveResumeConfirmation = () => {
		if (!saveResumeConfirmation) return;
		const message = saveResumeConfirmation.querySelector('.message');
		if (message && !message.querySelector('.ba-save-resume-heading')) {
			const heading = document.createElement('div');
			heading.className = 'ba-save-resume-heading';
			heading.innerHTML = '<span class="ba-save-resume-heading__eyebrow">SALVA LA SEGNALAZIONE</span><h2>Riprendi la compilazione più tardi</h2><p>Conserva il link personale oppure invialo al tuo indirizzo email. Potrai tornare esattamente alla compilazione salvata.</p>';
			message.prepend(heading);
		}
	};

	const syncSaveResumeMode = () => {
		if (!saveResumeConfirmation) return;
		const active = getComputedStyle(saveResumeConfirmation).display !== 'none';
		document.body.classList.toggle('ba-save-resume-mode',active);
		workspace?.classList.toggle('is-save-resume-mode',active);
		if (active) enhanceSaveResumeConfirmation();
	};

	if (saveResumeConfirmation) {
		new MutationObserver(() => requestAnimationFrame(syncSaveResumeMode))
			.observe(saveResumeConfirmation,{attributes:true,attributeFilter:['style','class']});
		syncSaveResumeMode();
	}

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
