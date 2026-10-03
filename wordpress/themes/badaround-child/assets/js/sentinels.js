(() => {
	'use strict';

	const form=document.querySelector('[data-ba-sentinel-form]');
	if(!form)return;

	const area=form.querySelector('[data-ba-sentinel-area]');
	const previewArea=document.querySelector('[data-ba-sentinel-preview-area]');
	const previewRadius=document.querySelector('[data-ba-sentinel-preview-radius]');
	const previewCategories=document.querySelector('[data-ba-sentinel-preview-categories]');
	const previewFrequency=document.querySelector('[data-ba-sentinel-preview-frequency]');

	const updateArea=()=>{
		if(previewArea) previewArea.textContent=(area?.value||'').trim()||'La tua zona';
	};
	const updateRadius=()=>{
		const selected=form.querySelector('input[name="radius"]:checked');
		if(previewRadius&&selected) previewRadius.textContent=selected.value;
	};
	const updateCategories=()=>{
		const selected=[...form.querySelectorAll('[data-ba-sentinel-categories] input:checked')].map(input=>input.value);
		if(previewCategories) previewCategories.textContent=selected.length?selected.join(', '):'Tutte le categorie';
	};
	const updateFrequency=()=>{
		const selected=form.querySelector('input[name="frequency"]:checked');
		if(previewFrequency&&selected) previewFrequency.textContent=selected.value;
	};

	area?.addEventListener('input',updateArea);
	form.querySelectorAll('input[name="radius"]').forEach(input=>input.addEventListener('change',updateRadius));
	form.querySelectorAll('[data-ba-sentinel-categories] input').forEach(input=>input.addEventListener('change',updateCategories));
	form.querySelectorAll('input[name="frequency"]').forEach(input=>input.addEventListener('change',updateFrequency));

	form.querySelector('[data-ba-sentinel-submit]')?.addEventListener('click',()=>{
		const button=form.querySelector('[data-ba-sentinel-submit]');
		if(!button)return;
		const original=button.textContent;
		button.textContent='Configurazione pronta';
		button.setAttribute('aria-live','polite');
		setTimeout(()=>{button.textContent=original;},1800);
	});

	updateArea();
	updateRadius();
	updateCategories();
	updateFrequency();
})();
