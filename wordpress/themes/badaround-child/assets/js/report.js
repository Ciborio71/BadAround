(() => {
	'use strict';

	const form = document.querySelector('#wpforms-6');
	if (!form) return;

	const intro = form.querySelector('.badaround-form-intro');
	if (intro) {
		const title = intro.querySelector('h2');
		const paragraphs = intro.querySelectorAll('p');
		if (title) title.textContent = 'Cosa è successo?';
		if (paragraphs[0]) paragraphs[0].textContent = 'Scegli la situazione più vicina al tuo caso. Mostreremo solo le domande necessarie.';
	}

	const iconSvgs = {
		'veicolo o mobilità': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h16l-1.5-5h-13L4 13Zm1 0v5m14-5v5M7 18h10M7 8l1.2-3h7.6L17 8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
		'degrado urbano': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 20h18L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8"/></svg>',
		'sicurezza o comportamento sospetto': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2M9 7a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
		'animale': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12c-2 0-3.5 1.4-3.5 3.2C4.5 18 7.6 20 12 20s7.5-2 7.5-4.8C19.5 13.4 18 12 16 12m-8 0c.7-2.1 2.1-3.3 4-3.3s3.3 1.2 4 3.3M6.5 8.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM12 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
		'oggetto smarrito o ritrovato': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v12H7zM9 7V5h6v2M9 11h6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
		'pericolo territoriale': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 20h18L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8"/></svg>',
		'problema o disservizio di quartiere': '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9l8-5 8 5v10M8 19v-5h8v5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
		'altro': '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/></svg>'
	};

	const initialChoices = form.querySelectorAll('.badaround-event-category .wpforms-field-label-inline');
	initialChoices.forEach((label) => {
		if (label.querySelector('.ba-choice-icon')) return;
		const key = label.textContent.trim().toLowerCase();
		const icon = document.createElement('span');
		icon.className = 'ba-choice-icon';
		icon.setAttribute('aria-hidden', 'true');
		icon.innerHTML = iconSvgs[key] || iconSvgs.altro;
		label.prepend(icon);
	});

	const indicator = form.querySelector('.wpforms-page-indicator');
	if (indicator && !form.querySelector('.ba-mobile-step-summary')) {
		const summary = document.createElement('div');
		summary.className = 'ba-mobile-step-summary';
		indicator.before(summary);

		const updateSummary = () => {
			const pages = [...indicator.querySelectorAll('.wpforms-page-indicator-page')];
			const active = indicator.querySelector('.wpforms-page-indicator-page.active');
			const index = Math.max(0, pages.indexOf(active));
			const title = active?.querySelector('.wpforms-page-indicator-page-title')?.textContent?.trim() || '';
			summary.innerHTML = '<span>Passaggio ' + (index + 1) + ' di ' + pages.length + '</span><span>' + title + '</span>';
		};

		updateSummary();
		new MutationObserver(updateSummary).observe(indicator, { attributes:true, subtree:true, attributeFilter:['class'] });
	}

	const relationshipField = [...form.querySelectorAll('.wpforms-field')].find((field) => {
		const label = field.querySelector('legend, .wpforms-field-label');
		return label && label.textContent.trim().toLowerCase().includes('qual è il tuo rapporto con l’evento');
	});

	const initialRadios = [...form.querySelectorAll('.badaround-event-category input[type="radio"]')];
	if (relationshipField && initialRadios.length) {
		relationshipField.classList.add('ba-relationship-field');

		const syncRelationshipVisibility = () => {
			const selected = initialRadios.some((radio) => radio.checked);
			relationshipField.hidden = !selected;
			relationshipField.setAttribute('aria-hidden', selected ? 'false' : 'true');
		};

		syncRelationshipVisibility();
		initialRadios.forEach((radio) => radio.addEventListener('change', syncRelationshipVisibility));
	}


	/* WPForms Save & Resume is not always rendered inside the page footer.
	 * Normalize the markup so the primary action can sit right-aligned
	 * with Save & Resume directly underneath it. */
	const normalizePageActions = () => {
		const pages = [...form.querySelectorAll('.wpforms-page')];
		pages.forEach((page) => {
			const footer = page.querySelector('.wpforms-page-footer');
			if (!footer) return;

			const next = footer.querySelector('.wpforms-page-next, button[type="submit"]');
			let save = page.querySelector('.wpforms-save-resume-button');

			if (!save) {
				const candidate = form.querySelector('.wpforms-save-resume-button:not([data-ba-positioned])');
				if (candidate) save = candidate;
			}

			if (save) {
				const wrapper = save.closest('.wpforms-save-resume-block, .wpforms-save-resume-container') || save;
				if (wrapper.parentElement !== footer) footer.appendChild(wrapper);
				save.dataset.baPositioned = '1';
				wrapper.classList?.add('ba-save-resume-slot');
			}

			if (next) next.classList.add('ba-primary-next');
		});
	};

	normalizePageActions();
	new MutationObserver(normalizePageActions).observe(form, { childList:true, subtree:true });

})();
