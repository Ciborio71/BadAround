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
})();
