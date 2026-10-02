(() => {
	'use strict';

	const filters=[...document.querySelectorAll('[data-ba-map-filter]')];
	filters.forEach((button)=>{
		button.addEventListener('click',()=>{
			filters.forEach((item)=>{
				item.classList.remove('is-active');
				item.setAttribute('aria-pressed','false');
			});
			button.classList.add('is-active');
			button.setAttribute('aria-pressed','true');
		});
	});

	const panel=document.querySelector('[data-ba-map-results]');
	const openButton=document.querySelector('[data-ba-map-open]');
	const closeButton=document.querySelector('[data-ba-map-close]');
	const backdrop=document.querySelector('[data-ba-map-backdrop]');

	const openPanel=()=>{
		if(!panel)return;
		panel.classList.add('is-open');
		if(backdrop){
			backdrop.hidden=false;
			backdrop.classList.add('is-open');
		}
		document.body.classList.add('ba-map-sheet-open');
	};

	const closePanel=()=>{
		if(!panel)return;
		panel.classList.remove('is-open');
		if(backdrop){
			backdrop.classList.remove('is-open');
			backdrop.hidden=true;
		}
		document.body.classList.remove('ba-map-sheet-open');
	};

	openButton?.addEventListener('click',openPanel);
	closeButton?.addEventListener('click',closePanel);
	backdrop?.addEventListener('click',closePanel);
	document.addEventListener('keydown',(event)=>{
		if(event.key==='Escape')closePanel();
	});

	const locate=document.querySelector('[data-ba-map-locate]');
	locate?.addEventListener('click',()=>{
		locate.classList.add('is-active');
		locate.querySelector('span').textContent='Posizione rilevata';
		setTimeout(()=>{
			locate.classList.remove('is-active');
			locate.querySelector('span').textContent='Vicino a me';
		},1800);
	});
})();
