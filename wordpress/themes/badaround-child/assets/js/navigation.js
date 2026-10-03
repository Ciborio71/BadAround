(() => {
	'use strict';
	const header=document.querySelector('[data-ba-header]');
	if(!header)return;
	const toggle=header.querySelector('.ba-menu-toggle');
	const menu=header.querySelector('#ba-mobile-menu');
	if(!toggle||!menu)return;

	const close=()=>{
		toggle.setAttribute('aria-expanded','false');
		toggle.setAttribute('aria-label','Apri menu');
		menu.hidden=true;
		document.body.classList.remove('ba-menu-open');
	};
	const open=()=>{
		toggle.setAttribute('aria-expanded','true');
		toggle.setAttribute('aria-label','Chiudi menu');
		menu.hidden=false;
		document.body.classList.add('ba-menu-open');
	};
	toggle.addEventListener('click',()=>toggle.getAttribute('aria-expanded')==='true'?close():open());
	menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));
	document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
	window.matchMedia('(min-width: 861px)').addEventListener('change',e=>{if(e.matches)close();});
})();
