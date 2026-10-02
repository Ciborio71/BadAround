(() => {
	'use strict';

	const root = document.getElementById('ba-territory-selector');
	if (!root || typeof BadAroundTerritory === 'undefined') return;

	const selects = Array.from(root.querySelectorAll('select[data-level]'));
	const hidden = document.getElementById('ba-territory-term-id');
	const selectedPath = JSON.parse(root.dataset.path || '[]');

	const resetAfter = (index) => {
		selects.slice(index + 1).forEach((select) => {
			select.innerHTML = `<option value="">${select.dataset.level}</option>`;
			select.disabled = true;
		});
	};

	const load = async (select, parent = 0) => {
		const url = new URL(BadAroundTerritory.ajaxUrl);
		url.searchParams.set('action', 'ba_get_territory_children');
		url.searchParams.set('nonce', BadAroundTerritory.nonce);
		url.searchParams.set('parent', String(parent));
		url.searchParams.set('level', select.dataset.level);

		const response = await fetch(url.toString(), { credentials: 'same-origin' });
		const payload = await response.json();
		if (!payload.success) throw new Error('Territory lookup failed');

		const label = select.options[0]?.textContent || select.dataset.level;
		select.innerHTML = `<option value="">${label}</option>`;
		payload.data.forEach(({ id, name }) => select.add(new Option(name, id)));
		select.disabled = false;
	};

	selects.forEach((select, index) => {
		select.addEventListener('change', async () => {
			resetAfter(index);
			hidden.value = select.value || (index > 0 ? selects[index - 1].value : '');
			const next = selects[index + 1];
			if (next && select.value) {
				try { await load(next, Number(select.value)); } catch (error) { /* Keep the parent selection. */ }
			}
		});
	});

	const restorePath = async () => {
		let parent = 0;
		for (let index = 0; index < selects.length; index += 1) {
			await load(selects[index], parent);
			if (!selectedPath[index]) break;
			selects[index].value = String(selectedPath[index]);
			parent = selectedPath[index];
		}
	};

	restorePath().catch(() => { selects[0].disabled = true; });
})();
