(() => {
	'use strict';

	const form = document.querySelector('[data-ba-sentinel-form]');
	if (!form) return;

	const territory = form.querySelector('[data-ba-sentinel-territory]');
	const category = form.querySelector('[data-ba-sentinel-category]');
	const eventType = form.querySelector('[data-ba-sentinel-event-type]');
	const email = form.querySelector('[data-ba-sentinel-email]');
	const button = form.querySelector('[data-ba-sentinel-submit]');
	const message = form.querySelector('[data-ba-sentinel-message]');
	const previewArea = document.querySelector('[data-ba-sentinel-preview-area]');
	const previewCategory = document.querySelector('[data-ba-sentinel-preview-category]');
	const previewEventType = document.querySelector('[data-ba-sentinel-preview-event-type]');

	const selectedText = (select, fallback) => {
		const option = select?.options?.[select.selectedIndex];
		return option && option.value ? option.textContent.trim() : fallback;
	};

	const updatePreview = () => {
		if (previewArea) previewArea.textContent = selectedText(territory, 'Seleziona un territorio');
		if (previewCategory) previewCategory.textContent = selectedText(category, 'Tutte le categorie');
		if (previewEventType) previewEventType.textContent = selectedText(eventType, 'Tutte le tipologie');
	};

	const filterEventTypes = () => {
		const categoryId = category?.value || '';
		let currentVisible = false;
		[...eventType.options].forEach((option, index) => {
			if (index === 0) {
				option.hidden = false;
				return;
			}
			const visible = !categoryId || option.dataset.parent === categoryId;
			option.hidden = !visible;
			if (visible && option.selected) currentVisible = true;
		});
		if (!currentVisible && eventType.value) eventType.value = '';
		updatePreview();
	};

	territory?.addEventListener('change', updatePreview);
	category?.addEventListener('change', filterEventTypes);
	eventType?.addEventListener('change', updatePreview);

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (!form.reportValidity()) return;

		button.disabled = true;
		button.textContent = 'Creazione…';
		message.hidden = true;
		message.className = 'ba-sentinel-form__message';

		const payload = {
			territory_term_id: Number(territory.value || 0),
			category_term_id: Number(category.value || 0),
			event_type_term_id: Number(eventType.value || 0),
			email: (email.value || '').trim()
		};

		try {
			const response = await fetch(window.BadAroundSentinels.endpoint, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				credentials: 'same-origin',
				body: JSON.stringify(payload)
			});
			const data = await response.json();
			if (!response.ok) {
				throw new Error(data?.message || 'Non è stato possibile creare la Sentinella.');
			}

			message.textContent = data.message || 'Controlla la tua email per confermare la Sentinella.';
			message.classList.add('is-success');
			message.hidden = false;
			email.value = '';
		} catch (error) {
			message.textContent = error.message || 'Si è verificato un errore. Riprova.';
			message.classList.add('is-error');
			message.hidden = false;
		} finally {
			button.disabled = false;
			button.textContent = 'Crea Sentinella';
		}
	});

	filterEventTypes();
	updatePreview();
})();
