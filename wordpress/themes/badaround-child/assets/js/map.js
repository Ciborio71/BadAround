(() => {
	'use strict';

	const config = window.BadAroundMap || {};
	const mapNodes = Array.from(document.querySelectorAll('[data-ba-map]'));
	if (!mapNodes.length) return;

	const categoryByTerm = config.categoryByTerm || {};
	const categories = {
		'veicoli': { color: '#1677E8', dark: '#0D55B6', icon: 'vehicle' },
		'case-e-attivita': { color: '#C9153D', dark: '#97102E', icon: 'property' },
		'pericoli': { color: '#F57C00', dark: '#C45D00', icon: 'hazard' },
		'spazi-pubblici': { color: '#0AA184', dark: '#067763', icon: 'public' },
		'animali': { color: '#7040C8', dark: '#503096', icon: 'animal' },
		'oggetti-e-documenti': { color: '#E3A415', dark: '#AA780C', icon: 'object' },
	};
	const neutral = { color: '#8292A8', dark: '#627187', icon: 'check' };
	const clusterColor = '#263B63';
	let googleMapsPromise;

	const loadGoogleMaps = () => {
		if (!config.hasMaps || !config.mapsUrl) return Promise.resolve(false);
		if (window.google?.maps) return Promise.resolve(true);
		if (googleMapsPromise) return googleMapsPromise;

		googleMapsPromise = new Promise((resolve, reject) => {
			const callbackName = '__badaroundGoogleMapsReady';
			const finish = () => {
				delete window[callbackName];
				resolve(Boolean(window.google?.maps));
			};
			const fail = () => {
				delete window[callbackName];
				reject(new Error('Google Maps non disponibile'));
			};

			window[callbackName] = finish;
			const script = document.createElement('script');
			const url = new URL(config.mapsUrl, window.location.origin);
			url.searchParams.set('callback', callbackName);
			script.src = url.toString();
			script.async = true;
			script.dataset.baGoogleMaps = '1';
			script.onerror = fail;
			document.head.appendChild(script);
		});

		return googleMapsPromise;
	};

	const baseMapStyles = [
		{
			featureType: 'poi',
			elementType: 'all',
			stylers: [{ visibility: 'off' }],
		},
		{
			featureType: 'transit',
			elementType: 'all',
			stylers: [{ visibility: 'off' }],
		},
		{
			featureType: 'road',
			elementType: 'labels.icon',
			stylers: [{ visibility: 'off' }],
		},
	];

	const esc = (value) => String(value ?? '')
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');

	const text = (value) => document.createTextNode(value || '');

	const iconMarkup = (name) => {
		const common = 'fill="none" stroke="white" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"';
		switch (name) {
			case 'vehicle':
				return '<path d="M7 14h10l-1.4-4.2H8.4L7 14Zm1 0v3m8-3v3M9 17h6M9.2 9.8l1-2.8h3.6l1 2.8" ' + common + '/><circle cx="9" cy="14.5" r=".8" fill="white"/><circle cx="15" cy="14.5" r=".8" fill="white"/>';
			case 'property':
				return '<path d="m5 11 7-6 7 6v8H8v-7" ' + common + '/><path d="M13 14h6v5h-6zM14 14v-2h4v2" ' + common + '/>';
			case 'hazard':
				return '<path d="M12 4 3.8 19h16.4L12 4Z" ' + common + '/><path d="M12 9v5m0 2.5h.01" ' + common + '/>';
			case 'public':
				return '<path d="M4 14h9M5 11h8M6 14v4m6-4v4M17 8v10" ' + common + '/><path d="M17 8c-2.2 0-3.2-1.4-3.2-3 0-1.7 1.3-3 3.2-3s3.2 1.3 3.2 3c0 1.6-1 3-3.2 3Z" ' + common + '/>';
			case 'animal':
				return '<circle cx="8" cy="9" r="1.6" fill="white"/><circle cx="12" cy="7" r="1.7" fill="white"/><circle cx="16" cy="9" r="1.6" fill="white"/><circle cx="6.5" cy="13" r="1.4" fill="white"/><circle cx="17.5" cy="13" r="1.4" fill="white"/><path d="M8 17c0-2.3 1.8-4.2 4-4.2s4 1.9 4 4.2c0 1.6-1.2 2.3-2.5 2-.9-.2-2.1-.2-3 0C9.2 19.3 8 18.6 8 17Z" fill="white"/>';
			case 'object':
				return '<path d="M6 5h8v11H6zM8 8h4M8 11h3" ' + common + '/><circle cx="15.5" cy="15.5" r="3.5" ' + common + '/><path d="m18 18 2 2" ' + common + '/>';
			case 'check':
				return '<path d="m7 12 3.2 3.2L17.5 8" ' + common + '/>';
			default:
				return '<circle cx="12" cy="12" r="3" fill="white"/>';
		}
	};

	const pinSvg = (categorySlug, selected = false, resolved = false) => {
		const palette = resolved ? neutral : (categories[categorySlug] || neutral);
		const width = selected ? 64 : 48;
		const height = selected ? 78 : 60;
		const halo = selected
			? '<circle cx="32" cy="30" r="29" fill="' + palette.color + '" fill-opacity=".16" stroke="' + palette.color + '" stroke-opacity=".42" stroke-width="2"/>'
			: '';
		const tx = selected ? 8 : 0;
		const ty = selected ? 4 : 0;
		const pin = '<path d="M24 58C20.5 53 8 40.8 8 25.8 8 16 15.2 8 24 8s16 8 16 17.8C40 40.8 27.5 53 24 58Z" fill="' + palette.color + '" stroke="' + palette.dark + '" stroke-width="1.5"/><circle cx="24" cy="25" r="12.2" fill="' + palette.color + '"/><g transform="translate(12 13)">' + iconMarkup(resolved ? 'check' : palette.icon) + '</g>';
		const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">' + halo + '<g transform="translate(' + tx + ' ' + ty + ')">' + pin + '</g></svg>';
		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
			scaledSize: new google.maps.Size(width, height),
			anchor: new google.maps.Point(width / 2, height),
		};
	};

	const clusterSvg = (count) => {
		const label = count > 99 ? '99+' : String(count);
		const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21" fill="' + clusterColor + '" stroke="white" stroke-width="3"/><text x="24" y="29" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-size="' + (label.length > 2 ? 12 : 15) + '" font-weight="700" fill="white">' + esc(label) + '</text></svg>';
		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
			scaledSize: new google.maps.Size(48, 48),
			anchor: new google.maps.Point(24, 24),
		};
	};

	const userDotSvg = () => {
		const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28"><circle cx="14" cy="14" r="13" fill="#1677E8" fill-opacity=".18"/><circle cx="14" cy="14" r="8" fill="white"/><circle cx="14" cy="14" r="6" fill="#1677E8"/></svg>';
		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
			scaledSize: new google.maps.Size(28, 28),
			anchor: new google.maps.Point(14, 14),
		};
	};

	const categoryFor = (item) => {
		const id = item?.event_type?.id;
		const slug = id ? categoryByTerm[String(id)] : '';
		return categories[slug] ? slug : '';
	};

	const isResolved = (item) => ['resolved', 'risolto', 'closed'].includes(String(item?.event_status || '').toLowerCase());

	// Only an explicit approved mode conveys exactness. Legacy geometry keeps its renderer.
	const publicGeometry = (geo) => {
		if (!geo || geo.lat === null || geo.lng === null || geo.lat === '' || geo.lng === '') return null;
		const lat = Number(geo.lat), lng = Number(geo.lng);
		if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
		const mode = geo.mode || 'legacy';
		if (!['exact_point', 'approximate_area', 'legacy', 'point', 'street', 'area', 'municipality'].includes(mode)) return null;
		const radius = Number(geo.radius_meters ?? geo.radius_m);
		if (mode === 'approximate_area' && (!Number.isFinite(radius) || radius <= 0)) return null;
		return { position: { lat, lng }, mode, radius };
	};

	const projectionIcon = (entry, active = false) => {
		const { category, resolved, mode } = entry;
		if (mode !== 'exact_point' && mode !== 'approximate_area') return pinSvg(category, active, resolved);
		const palette = resolved ? neutral : (categories[category] || neutral);
		const icon = iconMarkup(resolved ? 'check' : palette.icon);
		let svg, width, height, anchor;
		if (mode === 'exact_point') {
			width = 56; height = 68;
			// The path terminates at (28,64); halo and state changes never move that tip.
			svg = (active ? '<circle cx="28" cy="26" r="25" fill="' + palette.color + '" fill-opacity=".16"/>' : '')
				+ '<path d="M28 64C23 57 9 43 9 27a19 19 0 1 1 38 0c0 16-14 30-19 37Z" fill="' + palette.color + '" stroke="' + palette.dark + '" stroke-width="1.5"/>'
				+ '<circle cx="28" cy="27" r="13" fill="' + palette.dark + '" stroke="white" stroke-width="1.5"/>'
				+ '<g transform="translate(16 15)">' + icon + '</g>';
			anchor = new google.maps.Point(28, 64);
		} else {
			width = 48; height = 48;
			svg = (active ? '<circle cx="24" cy="24" r="23" fill="' + palette.color + '" fill-opacity=".18"/>' : '')
				+ '<circle cx="24" cy="24" r="18" fill="' + palette.color + '" stroke="white" stroke-width="3"/>'
				+ '<circle cx="24" cy="24" r="14" fill="' + palette.dark + '"/>'
				+ '<g transform="translate(12 12)">' + icon + '</g>';
			anchor = new google.maps.Point(24, 24);
		}
		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">' + svg + '</svg>'),
			scaledSize: new google.maps.Size(width, height), anchor,
		};
	};

	const groundShadow = () => ({
		url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="12" viewBox="0 0 24 12"><defs><filter id="blur"><feGaussianBlur stdDeviation="1"/></filter></defs><ellipse cx="12" cy="6" rx="8" ry="3" fill="#071a38" opacity=".35" filter="url(#blur)"/></svg>'),
		scaledSize: new google.maps.Size(24, 12), anchor: new google.maps.Point(12, 6),
	});

	const createProjection = (map, item, geo, category, active = false) => {
		const geometry = publicGeometry(geo);
		if (!geometry) return null;
		const entry = { ...geometry, item, category, resolved: isResolved(item), layers: [] };
		const label = geometry.mode === 'exact_point' ? "Posizione esatta dell'evento"
			: geometry.mode === 'approximate_area' ? "Area approssimativa dell'evento: raggio " + geometry.radius + ' metri'
			: "Posizione pubblica approssimata dell'evento";
		entry.marker = new google.maps.Marker({
			map, position: entry.position, title: label + ': ' + (item.title || 'BadAround'),
			icon: projectionIcon(entry, active), zIndex: active ? 300 : 100,
			optimized: false,
		});
		if (entry.mode === 'exact_point') {
			entry.shadow = new google.maps.Marker({
				map, position: entry.position, icon: groundShadow(), clickable: false,
				zIndex: 90, optimized: false,
			});
			entry.layers.push(entry.shadow);
		} else if (Number.isFinite(entry.radius) && (entry.mode === 'approximate_area' || entry.radius >= 100)) {
			const palette = entry.resolved ? neutral : (categories[category] || neutral);
			entry.circle = new google.maps.Circle({
				map, center: entry.position, radius: entry.radius,
				clickable: entry.mode === 'approximate_area',
				strokeColor: palette.color, strokeOpacity: .65, strokeWeight: 1.5,
				fillColor: palette.color, fillOpacity: .1, zIndex: 10,
			});
			entry.layers.push(entry.circle);
		}
		entry.marker.addListener('mouseover', () => entry.marker.setIcon(projectionIcon(entry, true)));
		entry.marker.addListener('mouseout', () => entry.marker.setIcon(projectionIcon(entry, Boolean(entry.selected))));
		return entry;
	};

	const showProjection = (entry, map) => {
		entry.marker.setMap(map);
		entry.layers.forEach((layer) => layer.setMap(map));
	};

	const buildPreviewContent = (item) => {
		const wrap = document.createElement('div');
		wrap.className = 'ba-map-preview-card';

		if (item.event_type?.name) {
			const badge = document.createElement('span');
			badge.className = 'ba-badge ba-badge--info';
			badge.appendChild(text(item.event_type.name));
			wrap.appendChild(badge);
		}

		const title = document.createElement('h3');
		title.appendChild(text(item.title));
		wrap.appendChild(title);

		const metaParts = [item.occurred_date, item.occurred_time, item.public_place_name || item.territory?.name].filter(Boolean);
		if (metaParts.length) {
			const meta = document.createElement('p');
			meta.className = 'ba-map-preview-card__meta';
			meta.appendChild(text(metaParts.join(' · ')));
			wrap.appendChild(meta);
		}

		if (item.excerpt) {
			const excerpt = document.createElement('p');
			excerpt.className = 'ba-map-preview-card__excerpt';
			excerpt.appendChild(text(item.excerpt));
			wrap.appendChild(excerpt);
		}

		const link = document.createElement('a');
		link.className = 'ba-button ba-map-preview-card__cta';
		link.href = item.permalink;
		link.appendChild(text('Vedi dettaglio'));
		wrap.appendChild(link);

		return wrap;
	};

	const renderList = (items, total = items.length) => {
		const list = document.querySelector('[data-ba-map-list]');
		const count = document.querySelector('[data-ba-map-count]');
		const mobileCount = document.querySelector('[data-ba-map-mobile-count]');
		if (count) count.textContent = `${total} ${total === 1 ? 'risultato' : 'risultati'}`;
		if (mobileCount) mobileCount.textContent = `${total} ${total === 1 ? 'risultato' : 'risultati'}`;
		if (!list) return;

		list.replaceChildren();
		if (!items.length) {
			const box = document.createElement('div');
			box.className = 'ba-map-empty';
			const heading = document.createElement('h3');
			heading.appendChild(text('Nessuna segnalazione pubblicata'));
			const p = document.createElement('p');
			p.appendChild(text('Quando saranno disponibili eventi pubblici compariranno qui.'));
			box.append(heading, p);
			list.appendChild(box);
			return;
		}

		items.forEach((item) => {
			const card = document.createElement('article');
			const category = categoryFor(item);
			card.className = 'ba-card ba-event-card ba-map-event-card' + (category ? ' ba-event-card--category-' + category : '');
			card.dataset.baEventId = String(item.id || '');
			if (item.thumbnail || config.placeholder) {
				const media = document.createElement('a');
				media.className = 'ba-event-card__media';
				media.href = item.permalink;
				media.tabIndex = -1;
				media.setAttribute('aria-hidden', 'true');
				const img = document.createElement('img');
				img.src = item.thumbnail || config.placeholder;
				img.alt = '';
				img.loading = 'lazy';
				img.decoding = 'async';
				media.appendChild(img);
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

			const link = document.createElement('a');
			link.className = 'ba-event-card__link';
			link.href = item.permalink;
			link.appendChild(text('Vedi dettaglio →'));
			body.appendChild(link);
			card.appendChild(body);
			list.appendChild(card);
		});
	};

	const bindResultsPanel = () => {
		const panel = document.querySelector('[data-ba-map-results]');
		const openButton = document.querySelector('[data-ba-map-open]');
		const closeButton = document.querySelector('[data-ba-map-close]');
		const backdrop = document.querySelector('[data-ba-map-backdrop]');
		if (!panel) return;

		const openPanel = () => {
			panel.classList.add('is-open');
			if (backdrop) {
				backdrop.hidden = false;
				backdrop.classList.add('is-open');
			}
			document.body.classList.add('ba-map-sheet-open');
		};
		const closePanel = () => {
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
	};

	const initMap = (node, items) => {
		const context = node.dataset.baMapContext || 'full';
		const status = node.parentElement?.querySelector('[data-ba-map-status]') || document.querySelector('[data-ba-map-status]');
		const preview = node.parentElement?.querySelector('[data-ba-map-preview]');
		const previewContent = preview?.querySelector('[data-ba-map-preview-content]');
		const previewClose = preview?.querySelector('[data-ba-map-preview-close]');

		const setStatus = (message) => {
			if (!status) return;
			status.textContent = message || '';
			status.hidden = !message;
		};

		if (!config.hasMaps || !window.google?.maps) {
			setStatus('Mappa non disponibile. Puoi continuare a usare la ricerca manuale.');
			return;
		}

		if (context === 'detail') {
			const geo = {
				lat: node.dataset.baEventLat, lng: node.dataset.baEventLng,
				mode: node.dataset.baEventMode, radius_meters: node.dataset.baEventRadius,
			};
			const geometry = publicGeometry(geo);
			if (!geometry) { setStatus('Posizione pubblica non disponibile.'); return; }
			const item = items.find((candidate) => String(candidate.id) === String(node.dataset.baEventId))
				|| { title: document.querySelector('h1')?.textContent || 'BadAround', event_status: node.dataset.baEventStatus };
			const category = categoryFor(item) || node.dataset.baEventCategory || '';
			const map = new google.maps.Map(node, {
				center: geometry.position, zoom: geometry.mode === 'exact_point' ? 16 : 14,
				mapTypeControl: false, streetViewControl: false, styles: baseMapStyles,
				...window.BadAroundMapNavigation.options(),
			});
			window.BadAroundMapNavigation.mount(map, node);
			const entry = createProjection(map, item, geo, category, true);
			entry.selected = true;
			if (entry.circle) map.fitBounds(entry.circle.getBounds(), 32);
			return;
		}
		const map = new google.maps.Map(node, {
			center: { lat: 41.9028, lng: 12.4964 }, zoom: 7,
			mapTypeControl: false, streetViewControl: false, styles: baseMapStyles,
			...window.BadAroundMapNavigation.options(),
		});
		window.BadAroundMapNavigation.mount(map, node);
		const bounds = new google.maps.LatLngBounds();

		const infoWindow = new google.maps.InfoWindow();
		const eventMarkers = [];
		let clusterMarkers = [];
		let selected = null;
		let userMarker = null;
		let overlayReady = false;

		const overlay = new google.maps.OverlayView();
		overlay.onAdd = () => {};
		overlay.draw = () => {
			if (!overlayReady) {
				overlayReady = true;
				refreshClusters();
			}
		};
		overlay.onRemove = () => {};
		overlay.setMap(map);

		const setSelected = (entry) => {
			if (selected && selected !== entry) {
				selected.selected = false;
				selected.marker.setIcon(projectionIcon(selected, false));
				selected.marker.setZIndex(100);
			}
			selected = entry;
			entry.selected = true;
			entry.marker.setIcon(projectionIcon(entry, true));
			entry.marker.setZIndex(300);

			if (preview && previewContent) {
				previewContent.replaceChildren(buildPreviewContent(entry.item));
				preview.hidden = false;
				preview.classList.add('is-open');
			} else {
				const wrapper = buildPreviewContent(entry.item);
				infoWindow.setContent(wrapper);
				infoWindow.open({ map, anchor: entry.marker });
			}
		};

		const clearSelected = () => {
			if (selected) {
				selected.selected = false;
				selected.marker.setIcon(projectionIcon(selected, false));
				selected.marker.setZIndex(100);
				selected = null;
			}
			if (preview) {
				preview.classList.remove('is-open');
				preview.hidden = true;
			}
			infoWindow.close();
		};

		previewClose?.addEventListener('click', clearSelected);

		items.forEach((item) => {
			const geo = item.public_geo;
			if (!publicGeometry(geo)) return;
			const category = categoryFor(item);
			if (!category) {
				console.warn('BadAround map: categoria marker non risolta per evento', item.id);
				return;
			}
			const entry = createProjection(map, item, geo, category);
			eventMarkers.push(entry);
			const select = () => {
				setSelected(entry);
				const listCard = document.querySelector('[data-ba-event-id="' + String(item.id) + '"]');
				if (listCard && context === 'full') {
					document.querySelectorAll('.ba-map-event-card.is-selected').forEach((el) => el.classList.remove('is-selected'));
					listCard.classList.add('is-selected');
					listCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}
			};
			entry.marker.addListener('click', select);
			entry.circle?.addListener('click', select);
			bounds.extend(entry.position);
		});

		const removeClusters = () => {
			clusterMarkers.forEach((marker) => marker.setMap(null));
			clusterMarkers = [];
		};

		function refreshClusters() {
			if (!overlayReady || !eventMarkers.length) return;
			const projection = overlay.getProjection();
			if (!projection) return;

			removeClusters();
			eventMarkers.forEach((entry) => showProjection(entry, map));

			if (map.getZoom() >= 17) return;

			const grid = 64;
			const groups = new Map();
			eventMarkers.forEach((entry) => {
				const point = projection.fromLatLngToDivPixel(new google.maps.LatLng(entry.position));
				if (!point) return;
				const key = Math.floor(point.x / grid) + ':' + Math.floor(point.y / grid);
				if (!groups.has(key)) groups.set(key, []);
				groups.get(key).push(entry);
			});

			groups.forEach((group) => {
				if (group.length < 2) return;
				group.forEach((entry) => showProjection(entry, null));
				const center = group.reduce((acc, entry) => ({
					lat: acc.lat + entry.position.lat / group.length,
					lng: acc.lng + entry.position.lng / group.length,
				}), { lat: 0, lng: 0 });
				const cluster = new google.maps.Marker({
					map,
					position: center,
					title: `${group.length} segnalazioni`,
					icon: clusterSvg(group.length),
					zIndex: 30,
				});
				cluster.addListener('click', () => {
					const groupBounds = new google.maps.LatLngBounds();
					group.forEach((entry) => groupBounds.extend(entry.position));
					map.fitBounds(groupBounds, 56);
					if (group.length === 2 && map.getZoom() > 17) map.setZoom(17);
				});
				clusterMarkers.push(cluster);
			});
		}

		map.addListener('idle', refreshClusters);

		if (eventMarkers.length === 1) {
			map.setCenter(bounds.getCenter());
			map.setZoom(13);
		} else if (eventMarkers.length > 1) {
			map.fitBounds(bounds, 48);
		} else {
			setStatus('Gli eventi pubblicati non dispongono ancora di una posizione pubblica cartografabile.');
		}

		const geoButton = document.querySelector('[data-ba-geolocate]');
		const geoStatus = document.querySelector('[data-ba-geolocation-status]');
		if (geoButton && context === 'home') {
			const setGeoStatus = (message) => {
				if (geoStatus) geoStatus.textContent = message || '';
			};

			geoButton.addEventListener('click', () => {
				if (!navigator.geolocation) {
					setGeoStatus('La geolocalizzazione non è disponibile in questo browser. Usa la ricerca manuale.');
					return;
				}
				if (geoButton.dataset.baGeoDenied === '1') return;

				geoButton.disabled = true;
				setGeoStatus('Richiesta della posizione in corso…');

				navigator.geolocation.getCurrentPosition(
					(position) => {
						const current = {
							lat: Number(position.coords.latitude),
							lng: Number(position.coords.longitude),
						};
						if (!userMarker) {
							userMarker = new google.maps.Marker({
								map,
								position: current,
								title: 'La tua posizione attuale',
								icon: userDotSvg(),
								zIndex: 400,
							});
						} else {
							userMarker.setPosition(current);
							userMarker.setMap(map);
						}
						map.panTo(current);
						if (map.getZoom() < 14) map.setZoom(14);
						setGeoStatus('Posizione mostrata sulla mappa. Non viene salvata da BadAround.');
						geoButton.disabled = false;
					},
					(error) => {
						let message = 'Non è stato possibile determinare la posizione. Puoi usare la ricerca manuale.';
						if (error.code === error.PERMISSION_DENIED) {
							message = 'Permesso posizione non concesso. La mappa resta disponibile con la ricerca manuale.';
							geoButton.dataset.baGeoDenied = '1';
							geoButton.textContent = 'Posizione non disponibile';
						} else if (error.code === error.TIMEOUT) {
							message = 'La richiesta della posizione è scaduta. Puoi riprovare o usare la ricerca manuale.';
						} else if (error.code === error.POSITION_UNAVAILABLE) {
							message = 'Posizione temporaneamente non disponibile. Puoi usare la ricerca manuale.';
						}
						setGeoStatus(message);
						geoButton.disabled = error.code === error.PERMISSION_DENIED;
					},
					{
						enableHighAccuracy: false,
						timeout: 10000,
						maximumAge: 60000,
					}
				);
			});
		}
	};

	const loadDiscovery = async () => {
		if (!config.endpoint) {
			throw new Error('Servizio Discovery non disponibile');
		}

		const url = new URL(config.endpoint, window.location.origin);
		url.searchParams.set('per_page', String(config.limit || 100));
		const response = await fetch(url.toString(), {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		});
		if (!response.ok) throw new Error(`Discovery HTTP ${response.status}`);
		return response.json();
	};

	const load = async () => {
		const [discoveryResult, mapsResult] = await Promise.allSettled([
			loadDiscovery(),
			loadGoogleMaps(),
		]);

		if (discoveryResult.status === 'rejected') {
			console.error('BadAround map:', discoveryResult.reason);
			document.querySelectorAll('[data-ba-map-status]').forEach((el) => {
				el.textContent = 'Errore nel caricamento della mappa. Puoi continuare con la ricerca manuale.';
				el.hidden = false;
			});
			return;
		}

		const payload = discoveryResult.value;
		const items = Array.isArray(payload.items) ? payload.items : [];
		const total = Number.isFinite(Number(payload.total)) ? Number(payload.total) : items.length;
		renderList(items, total);

		if (mapsResult.status === 'rejected') {
			console.error('BadAround map:', mapsResult.reason);
		}
		mapNodes.forEach((node) => initMap(node, items));
	};

	bindResultsPanel();
	load();
})();
