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
			card.className = 'ba-card ba-event-card';
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

		const map = new google.maps.Map(node, {
			center: { lat: 41.9028, lng: 12.4964 },
			zoom: 7,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: context !== 'home',
			gestureHandling: context === 'home' ? 'cooperative' : 'greedy',
		});

		const bounds = new google.maps.LatLngBounds();
		const infoWindow = new google.maps.InfoWindow();
		const eventMarkers = [];
		const circles = [];
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
				selected.marker.setIcon(pinSvg(selected.category, false, selected.resolved));
				selected.marker.setZIndex(100);
			}
			selected = entry;
			entry.marker.setIcon(pinSvg(entry.category, true, entry.resolved));
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
				selected.marker.setIcon(pinSvg(selected.category, false, selected.resolved));
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
			if (!geo || !Number.isFinite(Number(geo.lat)) || !Number.isFinite(Number(geo.lng))) return;
			const category = categoryFor(item);
			if (!category) {
				console.warn('BadAround map: categoria marker non risolta per evento', item.id);
				return;
			}

			const position = { lat: Number(geo.lat), lng: Number(geo.lng) };
			const resolved = isResolved(item);
			const marker = new google.maps.Marker({
				map,
				position,
				title: item.title || 'Evento BadAround',
				icon: pinSvg(category, false, resolved),
				zIndex: 100,
				optimized: true,
			});
			const entry = { item, marker, position, category, resolved };
			eventMarkers.push(entry);

			marker.addListener('click', () => setSelected(entry));

			const radius = Number(geo.radius_m);
			if (Number.isFinite(radius) && radius >= 100) {
				const palette = categories[category];
				const circle = new google.maps.Circle({
					map,
					center: position,
					radius,
					clickable: false,
					strokeColor: palette.color,
					strokeOpacity: 0.5,
					strokeWeight: 1.5,
					fillColor: palette.color,
					fillOpacity: 0.1,
					zIndex: 10,
				});
				circles.push(circle);
			}

			bounds.extend(position);
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
			eventMarkers.forEach((entry) => entry.marker.setMap(map));

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
				group.forEach((entry) => entry.marker.setMap(null));
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

	const load = async () => {
		if (!config.endpoint) {
			document.querySelectorAll('[data-ba-map-status]').forEach((el) => {
				el.textContent = 'Servizio Discovery non disponibile.';
				el.hidden = false;
			});
			return;
		}

		try {
			const url = new URL(config.endpoint, window.location.origin);
			url.searchParams.set('per_page', String(config.limit || 100));
			const response = await fetch(url.toString(), {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
			});
			if (!response.ok) throw new Error(`Discovery HTTP ${response.status}`);
			const payload = await response.json();
			const items = Array.isArray(payload.items) ? payload.items : [];
			const total = Number.isFinite(Number(payload.total)) ? Number(payload.total) : items.length;

			renderList(items, total);
			mapNodes.forEach((node) => initMap(node, items));
		} catch (error) {
			console.error('BadAround map:', error);
			document.querySelectorAll('[data-ba-map-status]').forEach((el) => {
				el.textContent = 'Errore nel caricamento della mappa. Puoi continuare con la ricerca manuale.';
				el.hidden = false;
			});
		}
	};

	bindResultsPanel();
	load();
})();