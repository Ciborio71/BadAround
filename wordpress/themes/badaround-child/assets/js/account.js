(() => {
	'use strict';
	const tabs=[...document.querySelectorAll('[data-ba-account-tab]')];
	const panels=[...document.querySelectorAll('[data-ba-account-panel]')];
	if(!tabs.length||!panels.length)return;

	const activate=(name)=>{
		tabs.forEach((tab)=>{
			const active=tab.dataset.baAccountTab===name;
			tab.classList.toggle('is-active',active);
			tab.setAttribute('aria-pressed',active?'true':'false');
		});
		panels.forEach((panel)=>{
			const active=panel.dataset.baAccountPanel===name;
			panel.classList.toggle('is-active',active);
			panel.hidden=!active;
		});
	};

	tabs.forEach((tab)=>tab.addEventListener('click',()=>activate(tab.dataset.baAccountTab)));
	document.querySelectorAll('[data-ba-account-jump]').forEach((button)=>{
		button.addEventListener('click',()=>activate(button.dataset.baAccountJump));
	});
})();
