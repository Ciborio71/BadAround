(() => {
	'use strict';

	const config = window.BadAroundMap || {};
	const panel = document.querySelector('[data-ba-map-results]');
	const list = document.querySelector('[data-ba-map-list]');
	const count = document.querySelector('[data-ba-map-count]');
	const mobileCount = document.querySelector('[data-ba-map-mobile-count]');
	const status = document.querySelector('[data-ba-map-status]');
	const openButton = document.querySelector('[data-ba-map-open]');
	const closeButton = document.querySelector('[data-ba-map-close]');
	const backdrop = document.querySelector('[data-ba-map-backdrop]');
	const mapNode = document.getElementById('ba-google-map');

	const openPanel = () => {
		if (!panel) return;
		panel.classList.add('is-open');
		if (backdrop) {
			backdrop.hidden = false;
			backdrop.classList.add('is-open');
		}
		document.body.classList.add('ba-map-sheet-open');
	};

	const closePanel = () => {
		if (!panel) return;
		panel.classList.remove('is-open');
		if (backdrop) {
			backdrop.classList.remove('is-open');
			backdrop.hidden = true;
		}
		document.body.classList.remove('ba-map-sheet-open');
	};

	openButton?.addEventListener('click', openPanel);
	closeButton?.addEventListener('click', closePanel);
	backdrop?.addEventListener('click', closePanel);
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') closePanel();
	});

	const setStatus = (message) => {
		if (!status) return;
		if (!message) {
			status.hidden = true;
			status.textContent = '';
			return;
		}
		status.textContent = message;
		status.hidden = false;
	};

	const text = (value) => document.createTextNode(value || '');

	const renderEmpty = (title, body) => {
		if (!list) return;
		list.replaceChildren();
		const box = document.createElement('div');
		box.className = 'ba-map-empty';
		const icon = document.createElement('span');
		icon.setAttribute('aria-hidden', 'true');
		icon.textContent = '◎';
		const heading = document.createElement('h3');
		heading.appendChild(text(title));
		const paragraph = document.createElement('p');
		paragraph.appendChild(text(body));
		box.append(icon, heading, paragraph);
		list.appendChild(box);
	};

	const renderList = (items) => {
		if (!list) return;
		list.replaceChildren();

		if (!items.length) {
			renderEmpty('Nessuna segnalazione pubblicata', 'Quando saranno disponibili eventi pubblici compariranno qui.');
			return;
		}

		items.forEach((item) => {
			const card = document.createElement('article');
			card.className = 'ba-card ba-event-card';

			if (item.thumbnail) {
				const media = document.createElement('a');
				media.className = 'ba-event-card__media';
				media.href = item.permalink;
				const image = document.createElement('img');
				image.src = item.thumbnail;
				image.alt = item.title || '';
				image.loading = 'lazy';
				image.decoding = 'async';
				media.appendChild(image);
				card.appendChild(media);
			}

			const body = document.createElement('div');
			body.className = 'ba-event-card__body';

			if (item.event_type?.name) {
				const badge = document.createElement('span');
				badge.className = 'ba-badge ba-badge--info';
				badge.appendChild(text(item.event_type.name));
				body.appendChild(badge);
			}

			const heading = document.createElement('h3');
			heading.className = 'ba-event-card__title';
			const headingLink = document.createElement('a');
			headingLink.href = item.permalink;
			headingLink.appendChild(text(item.title));
			heading.appendChild(headingLink);
			body.appendChild(heading);

			const metaParts = [item.occurred_date, item.occurred_time, item.public_place_name || item.territory?.name].filter(Boolean);
			if (metaParts.length) {
				const meta = document.createElement('div');
				meta.className = 'ba-event-card__meta';
				meta.appendChild(text(metaParts.join(' · ')));
				body.appendChild(meta);
			}

			if (item.excerpt) {
				const excerpt = document.createElement('p');
				excerpt.className = 'ba-event-card__excerpt';
				excerpt.appendChild(text(item.excerpt));
				body.appendChild(excerpt);
			}

			const link = document.createElement('a');
			link.className = 'ba-event-card__link';
			link.href = item.permalink;
			link.appendChild(text('Vedi dettaglio →'));
			body.appendChild(link);

			card.appendChild(body);
			list.appendChild(card);
		});
	};

	const initMap = (items) => {
		if (!mapNode || !config.hasMaps || !window.google?.maps) {
			setStatus('Mappa non disponibile. Gli eventi pubblici restano consultabili nell’elenco.');
			return;
		}

		const map = new google.maps.Map(mapNode, {
			center: { lat: 41.9028, lng: 12.4964 },
			zoom: 7,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: true,
			gestureHandling: 'greedy',
		});

		const bounds = new google.maps.LatLngBounds();
		const infoWindow = new google.maps.InfoWindow();
		let markerCount = 0;

		items.forEach((item) => {
			const geo = item.public_geo;
			if (!geo || !Number.isFinite(Number(geo.lat)) || !Number.isFinite(Number(geo.lng))) return;

			const position = { lat: Number(geo.lat), lng: Number(geo.lng) };
			const marker = new google.maps.Marker({
				map,
				position,
				title: item.title || 'Evento BadAround',
			});

			if (Number(geo.radius_m) >= 100) {
				new google.maps.Circle({
					map,
					center: position,
					radius: Number(geo.radius_m),
					clickable: false,
					strokeOpacity: 0.28,
					strokeWeight: 1,
					fillOpacity: 0.06,
				});
			}

			marker.addListener('click', () => {
				const wrapper = document.createElement('div');
				wrapper.className = 'ba-map-infowindow';
				const title = document.createElement('strong');
				title.appendChild(text(item.title));
				const place = document.createElement('div');
				place.appendChild(text(item.public_place_name || item.territory?.name || 'Posizione pubblica'));
				const link = document.createElement('a');
				link.href = item.permalink;
				link.appendChild(text('Apri dettaglio'));
				wrapper.append(title, place, link);
				infoWindow.setContent(wrapper);
				infoWindow.open({ map, anchor: marker });
			});

			bounds.extend(position);
			markerCount += 1;
		});

		if (markerCount === 1) {
			map.setCenter(bounds.getCenter());
			map.setZoom(13);
		} else if (markerCount > 1) {
			map.fitBounds(bounds, 48);
		} else {
			setStatus('Gli eventi pubblicati non dispongono ancora di una posizione pubblica cartografabile.');
		}
	};

	const load = async () => {
		if (!config.endpoint) {
			renderEmpty('Mappa non disponibile', 'Il servizio Discovery non è configurato.');
			setStatus('Servizio Discovery non disponibile.');
			return;
		}

		try {
			const url = new URL(config.endpoint, window.location.origin);
			url.searchParams.set('per_page', String(config.limit || 100));
			const response = await fetch(url.toString(), { credentials: 'same-origin' });
			if (!response.ok) throw new Error(`Discovery HTTP ${response.status}`);
			const payload = await response.json();
			const items = Array.isArray(payload.items) ? payload.items : [];
			const total = Number(payload.total || 0);

			if (count) count.textContent = `${total} ${total === 1 ? 'risultato' : 'risultati'}`;
			if (mobileCount) mobileCount.textContent = `${total} ${total === 1 ? 'risultato' : 'risultati'}`;

			renderList(items);
			initMap(items);
		} catch (error) {
			console.error('BadAround map:', error);
			if (count) count.textContent = 'Errore';
			if (mobileCount) mobileCount.textContent = 'Errore';
			renderEmpty('Impossibile caricare gli eventi', 'Riprova più tardi.');
			setStatus('Errore nel caricamento della mappa.');
		}
	};

	load();
})();
