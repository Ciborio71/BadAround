(() => {
	'use strict';
	document.querySelectorAll('[data-ba-filter]').forEach((button) => {
		button.addEventListener('click', () => {
			button.parentElement.querySelectorAll('[data-ba-filter]').forEach((item) => item.setAttribute('aria-pressed', 'false'));
			button.setAttribute('aria-pressed', 'true');
		});
	});
})();

