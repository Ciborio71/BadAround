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
	const neutral = { color: '#8292A8', dark: '#627187', icon: 'neutral' };
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
	const editorialText = (value) => String(value || '')
		.replace(/\bO\b/g, 'o')
		.replace(/\bE\b/g, 'e');

	const pinGlyphs = {"neutral":"<g fill=\"#111\"><circle cx=\"12\" cy=\"12\" r=\"7\" fill=\"none\" stroke=\"#111\" stroke-width=\"2\"/><path d=\"M12 1v5m0 12v5M1 12h5m12 0h5\" fill=\"none\" stroke=\"#111\" stroke-width=\"2\"/><circle cx=\"12\" cy=\"12\" r=\"2\"/></g>","vehicle":"<g fill=\"#111\"><path fill-rule=\"evenodd\" d=\"M6 4h12l3 8v8h-4v-3H7v3H3v-8Zm1.5 3-1.8 5h12.6l-1.8-5Z\"/><circle cx=\"7\" cy=\"14.5\" r=\"1.4\" fill=\"#fff\"/><circle cx=\"17\" cy=\"14.5\" r=\"1.4\" fill=\"#fff\"/></g>","property":"<g fill=\"#111\"><path d=\"m1 11 11-9 11 9-2 2-1-1v10h-6v-7h-4v7H4V12l-1 1Z\"/></g>","hazard":"<g fill=\"#111\"><path d=\"M12 3 2 21h20Z\" fill=\"none\" stroke=\"#111\" stroke-width=\"2.3\" stroke-linejoin=\"round\"/><path d=\"M12 9v6\" fill=\"none\" stroke=\"#111\" stroke-width=\"2.6\" stroke-linecap=\"round\"/><circle cx=\"12\" cy=\"18\" r=\"1.2\"/></g>","public":"<g fill=\"#111\"><path d=\"M2 13h10v2H2zm0 4h10v2H2zm1 2h2v3H3zm6 0h2v3H9zm8-7h3v10h-3z\"/><path d=\"M18 2a4 4 0 0 0-4 4 4 4 0 0 0 0 7h8a4 4 0 0 0 0-7 4 4 0 0 0-4-4Z\"/></g>","animal":"<g fill=\"#111\"><ellipse cx=\"5\" cy=\"9\" rx=\"2.2\" ry=\"3\" transform=\"rotate(-25 5 9)\"/><ellipse cx=\"10\" cy=\"5\" rx=\"2.2\" ry=\"3\"/><ellipse cx=\"16\" cy=\"5\" rx=\"2.2\" ry=\"3\"/><ellipse cx=\"21\" cy=\"10\" rx=\"2.2\" ry=\"3\" transform=\"rotate(25 21 10)\"/><path d=\"M5 19c0-3 4-8 7-8s7 5 7 8c0 4-4 3-7 2-3 1-7 2-7-2Z\"/></g>","object":"<g fill=\"#111\"><path d=\"M4 2h9l4 4v4M4 2v18h5M8 7h4M8 11h3\" fill=\"none\" stroke=\"#111\" stroke-width=\"2.2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"m16 16-6 6-2-2 6-6Z\"/><circle cx=\"17\" cy=\"13\" r=\"5\"/><circle cx=\"18\" cy=\"12\" r=\"1.6\" fill=\"#fff\"/></g>","check":"<g fill=\"#111\"><path d=\"m4 12 5 5L20 6\" fill=\"none\" stroke=\"#111\" stroke-width=\"3\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></g>"};
	const iconMarkup = name => pinGlyphs[name] || pinGlyphs.neutral;
    const pinBody = (category, resolved=false) => {
        const key=resolved?'resolved':(categories[category]?category:'neutral');
        if(config.pinDesigns?.[key]?.body)return config.pinDesigns[key].body;
        const p=resolved?neutral:(categories[category]||neutral);
        return '<path d="M24 58C19 52 3 37 3 23a21 21 0 1 1 42 0c0 14-16 29-21 35Z" fill="'+p.color+'" stroke="#fff" stroke-width="1.6"/><circle cx="24" cy="23" r="15.3" fill="#fff"/><g transform="translate(12 11)">'+iconMarkup(resolved?'check':p.icon)+'</g>';
    };
    const pinSvg = (categorySlug, selected=false, resolved=false) => {
        const p=resolved?neutral:(categories[categorySlug]||neutral);
        const width=selected?64:48,height=selected?78:60,tx=selected?8:0,ty=selected?4:0;
        const halo=selected?'<circle cx="32" cy="27" r="29" fill="'+p.color+'" fill-opacity=".16"/>':'';
        const svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+width+'" height="'+height+'" viewBox="0 0 '+width+' '+height+'">'+halo+'<g transform="translate('+tx+' '+ty+')">'+pinBody(categorySlug,resolved)+'</g></svg>';
        return {url:'data:image/svg+xml;charset=UTF-8,'+encodeURIComponent(svg),scaledSize:new google.maps.Size(width,height),anchor:new google.maps.Point(24+tx,58+ty)};
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
                + '<g transform="translate(4 6)">'+pinBody(category,resolved)+'</g>';
			anchor = new google.maps.Point(28, 64);
		} else {
			width = 48; height = 48;
			svg = (active ? '<circle cx="24" cy="24" r="23" fill="' + palette.color + '" fill-opacity=".18"/>' : '')
				+ '<circle cx="24" cy="24" r="18" fill="' + palette.color + '" stroke="white" stroke-width="3"/>'
				+ '<circle cx="24" cy="24" r="14" fill="#fff"/>'
				+ '<g transform="translate(12 12)">' + icon + '</g>';
			anchor = new google.maps.Point(24, 24);
		}
		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">' + svg + '</svg>'),
			scaledSize: new google.maps.Size(width, height), anchor,
		};
	};

    const approximateItem = item => publicGeometry(item.public_geo)?.mode === 'approximate_area';
    const precisionLabel = item => approximateItem(item) ? 'Area approssimata · raggio '+publicGeometry(item.public_geo).radius+' m' : '';
    const hasUserImage = item => {
        if(!item.thumbnail)return false;
        // Recognise configured placeholders even when returned as a thumbnail.
        try{
            const actual=new URL(item.thumbnail,window.location.href);
            return ![config.placeholder,...Object.values(config.placeholders||{})].filter(Boolean).some(url=>{
                const fallback=new URL(url,window.location.href);
                return actual.origin===fallback.origin&&(actual.pathname===fallback.pathname||actual.pathname===fallback.pathname.replace(/\.svg$/,'-area.svg'));
            });
        }catch(_){return false;}
    };
    const cardImage = (item,category) => {
        if(hasUserImage(item))return item.thumbnail;
        const url=config.placeholders?.[category]||config.placeholder;
        return approximateItem(item)&&url?url.replace(/\.svg(?=\?|$)/,'-area.svg'):url;
    };
    const cardMedallion = (item,category) => {
        if(!approximateItem(item))return '<svg class="ba-pin-svg" viewBox="0 0 48 60" aria-hidden="true" focusable="false">'+pinBody(category,false)+'</svg>';
        const p=categories[category]||neutral;
        return '<svg class="ba-pin-svg ba-pin-svg--area" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="21" fill="'+p.color+'" stroke="#fff" stroke-width="1.6"/><circle cx="24" cy="24" r="15.3" fill="#fff"/><g transform="translate(12 12)">'+iconMarkup(p.icon)+'</g></svg>';
    };

	const groundShadow = () => ({
		url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="12" viewBox="0 0 24 12"><defs><filter id="blur"><feGaussianBlur stdDeviation="1"/></filter></defs><ellipse cx="12" cy="6" rx="8" ry="3" fill="#071a38" opacity=".35" filter="url(#blur)"/></svg>'),
		scaledSize: new google.maps.Size(24, 12), anchor: new google.maps.Point(12, 6),
	});

	const createProjection = (map, item, geo, category, active = false) => {
		const geometry = publicGeometry(geo);
		if (!geometry) return null;
		const entry = { ...geometry, item, category, resolved: isResolved(item), layers: [], filteredVisible: true };
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
		const category = categoryFor(item);
		const palette = categories[category] || neutral;
		const wrap = document.createElement('article');
		wrap.className = 'ba-card ba-event-card ba-event-card--home ba-map-preview-card' + (category ? ' ba-event-card--category-' + category : '');
		wrap.style.setProperty('--ba-card-accent', palette.color);
        wrap.classList.toggle('ba-event-card--approximate',approximateItem(item));

		const imageUrl = cardImage(item,category);
		if (imageUrl) {
			const media = document.createElement('a');
			media.className = 'ba-event-card__media';
			media.href = item.permalink;
			media.tabIndex = -1;
			media.setAttribute('aria-hidden', 'true');

			const img = document.createElement('img');
			img.src = imageUrl;
			img.alt = '';
			img.loading = 'lazy';
			img.decoding = 'async';
			media.appendChild(img);

			if(hasUserImage(item)){
			const medallion = document.createElement('span');
			medallion.className = 'ba-event-card__media-category';
			medallion.setAttribute('aria-hidden', 'true');
			medallion.innerHTML = cardMedallion(item,category);
			media.appendChild(medallion);
            }
			wrap.appendChild(media);
		} else {
			wrap.classList.add('ba-event-card--no-media');
		}

		const body = document.createElement('div');
		body.className = 'ba-event-card__body';

		if (item.event_type?.name) {
			const badge = document.createElement('span');
			badge.className = 'ba-badge ba-badge--info';
			badge.appendChild(text(item.event_type.name));
			body.appendChild(badge);
		}

		const title = document.createElement('h3');
		title.className = 'ba-event-card__title';
		const titleLink = document.createElement('a');
		titleLink.href = item.permalink;
		titleLink.appendChild(text(editorialText(item.title)));
		title.appendChild(titleLink);
		body.appendChild(title);

		const metaParts = [item.occurred_date, item.occurred_time, item.public_place_name || item.territory?.name, precisionLabel(item)].filter(Boolean);
		if (metaParts.length) {
			const meta = document.createElement('div');
			meta.className = 'ba-event-card__meta';
			meta.appendChild(text(metaParts.join(' · ')));
			body.appendChild(meta);
		}

		if (item.excerpt) {
			const excerpt = document.createElement('p');
			excerpt.className = 'ba-event-card__excerpt';
			excerpt.appendChild(text(editorialText(item.excerpt)));
			body.appendChild(excerpt);
		}

		const link = document.createElement('a');
		link.className = 'ba-event-card__link';
		link.href = item.permalink;
		link.appendChild(text('Visualizza segnalazione '));
		const arrow = document.createElement('span');
		arrow.setAttribute('aria-hidden','true');
		arrow.textContent = '→';
		link.appendChild(arrow);
		body.appendChild(link);

		wrap.appendChild(body);
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
			card.classList.toggle('ba-event-card--approximate',approximateItem(item));
			card.dataset.baEventId = String(item.id || '');
			card.tabIndex = 0;
			card.setAttribute('role','button');
			card.setAttribute('aria-label','Mostra sulla mappa: ' + editorialText(item.title || 'Segnalazione BadAround'));
			if (item.thumbnail || config.placeholders?.[category] || config.placeholder) {
				const media = document.createElement('div');
				media.className = 'ba-event-card__media';
				media.setAttribute('aria-hidden', 'true');
				const img = document.createElement('img');
				img.src = cardImage(item,category);
				img.alt = '';
				img.loading = 'lazy';
				img.decoding = 'async';
				media.appendChild(img);
                if(hasUserImage(item)){const medallion=document.createElement('span');medallion.className='ba-event-card__media-category';medallion.innerHTML=cardMedallion(item,category);media.appendChild(medallion);}
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
			heading.appendChild(text(editorialText(item.title)));
			body.appendChild(heading);

			const metaParts = [item.occurred_date, item.occurred_time, item.public_place_name || item.territory?.name, precisionLabel(item)].filter(Boolean);
			if (metaParts.length) {
				const meta = document.createElement('div');
				meta.className = 'ba-event-card__meta';
				meta.appendChild(text(metaParts.join(' · ')));
				body.appendChild(meta);
			}

			const link = document.createElement('span');
			link.className = 'ba-event-card__link ba-map-event-card__select-hint';
			link.appendChild(text('Mostra sulla mappa →'));
			body.appendChild(link);
			card.appendChild(body);
			const selectFromList = () => {
				document.dispatchEvent(new CustomEvent('badaround:list-select', { detail: { id: String(item.id || '') } }));
			};
			card.addEventListener('click', selectFromList);
			card.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					selectFromList();
				}
			});
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
		const canvas = node.parentElement;
		let preview = canvas?.querySelector(':scope > [data-ba-map-preview]') || null;
		if (context === 'full' && canvas && !preview) {
			preview = document.createElement('aside');
			preview.className = 'ba-map-preview ba-home-map__preview';
			preview.dataset.baMapPreview = '';
			preview.hidden = true;
			preview.setAttribute('aria-live','polite');
			preview.setAttribute('aria-label','Anteprima evento selezionato');

			const close = document.createElement('button');
			close.type = 'button';
			close.className = 'ba-map-preview__close';
			close.dataset.baMapPreviewClose = '';
			close.setAttribute('aria-label','Chiudi anteprima');
			close.textContent = '×';

			const content = document.createElement('div');
			content.dataset.baMapPreviewContent = '';
			preview.append(close,content);
			canvas.appendChild(preview);
		}
		if (context === 'full' && preview && canvas && preview.parentElement !== canvas) {
			canvas.appendChild(preview);
		}
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

		const positionPreview = (entry) => {
			if (!preview || !entry || !overlayReady) return;
			const projection = overlay.getProjection();
			if (!projection) return;
			const point = projection.fromLatLngToContainerPixel(new google.maps.LatLng(entry.position));
			if (!point) return;

			requestAnimationFrame(() => {
				if (!preview || preview.hidden) return;
				const canvasRect = canvas.getBoundingClientRect();
				const width = Math.min(preview.offsetWidth || 420, Math.max(220, canvasRect.width - 24));
				const height = preview.offsetHeight || 160;
				const safe = 12;
				const controlRail = context === 'full' ? 118 : 12;
				const markerHalfWidth = entry.mode === 'exact_point' ? 34 : entry.mode === 'approximate_area' ? 28 : 38;
				// Exact/legacy pins are anchored on their bottom tip, not their visual centre.
				// Reserve the full rendered marker height above the geographic point.
				const markerAbove = entry.mode === 'exact_point' ? 76 : entry.mode === 'approximate_area' ? 30 : 86;
				const markerBelow = entry.mode === 'approximate_area' ? 30 : 10;
				const gap = 24;
				const clearanceAbove = markerAbove + gap;
				const clearanceBelow = markerBelow + gap;
				const maxLeft = Math.max(safe, canvasRect.width - width - controlRail - safe);

				let left = point.x - width / 2;
				left = Math.min(Math.max(safe, left), maxLeft);

				const canFitAbove = point.y - clearanceAbove - height >= safe;
				const canFitBelow = point.y + clearanceBelow + height <= canvasRect.height - safe;

				let top;
				if (canFitAbove) {
					top = point.y - clearanceAbove - height;
				} else if (canFitBelow) {
					top = point.y + clearanceBelow;
				} else {
					top = Math.min(Math.max(safe, point.y - height / 2), Math.max(safe, canvasRect.height - height - safe));

					const markerLeft = point.x - markerHalfWidth;
					const markerRight = point.x + markerHalfWidth;
					const cardLeft = left;
					const cardRight = left + width;

					if (cardLeft < markerRight && cardRight > markerLeft) {
						const sideClearance = markerHalfWidth + gap;
						const leftCandidate = point.x - sideClearance - width;
						const rightCandidate = point.x + sideClearance;
						const canFitLeft = leftCandidate >= safe;
						const canFitRight = rightCandidate + width <= canvasRect.width - controlRail - safe;

						if (canFitRight) left = rightCandidate;
						else if (canFitLeft) left = leftCandidate;
					}
				}

				preview.style.left = Math.round(left) + 'px';
				preview.style.top = Math.round(top) + 'px';
				preview.style.right = 'auto';
				preview.style.bottom = 'auto';
			});
		};

		const focusEntryAboveFold = (entry) => {
			if (!entry) return;
			const targetZoom = entry.mode === 'exact_point' ? 16 : 15;

			// Keep the selected event at the native Google Maps centre.
			// No pixel compensation: the preview itself is positioned around the marker.
			map.setCenter(entry.position);
			map.setZoom(targetZoom);
			showProjection(entry, map);
			entry.marker.setZIndex(400);

			google.maps.event.addListenerOnce(map, 'idle', () => {
				showProjection(entry, map);
				entry.marker.setZIndex(400);
				positionPreview(entry);
			});
		};

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
				try {
					previewContent.replaceChildren(buildPreviewContent(entry.item));
					preview.hidden = false;
					preview.removeAttribute('hidden');
					preview.classList.add('is-open');
					preview.style.display = 'block';
					positionPreview(entry);
				} catch (error) {
					console.error('BadAround map preview:', error);
					previewContent.textContent = entry.item?.title || 'Segnalazione BadAround';
					preview.hidden = false;
					preview.removeAttribute('hidden');
					preview.classList.add('is-open');
					preview.style.display = 'block';
					positionPreview(entry);
				}
			} else if (context !== 'full') {
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
				preview.style.display = '';
				preview.hidden = true;
			}
			infoWindow.close();
		};

		previewClose?.addEventListener('click', clearSelected);

		document.addEventListener('badaround:list-select', (event) => {
			const target = event.detail?.id;
			const entry = eventMarkers.find((candidate) => String(candidate.item.id) === String(target));
			if (!entry) return;

			document.querySelectorAll('.ba-map-event-card.is-selected').forEach((el) => el.classList.remove('is-selected'));
			const listCard = document.querySelector('[data-ba-event-id="' + String(entry.item.id) + '"]');
			listCard?.classList.add('is-selected');

			setSelected(entry);
			focusEntryAboveFold(entry);
		});

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
					const list = document.querySelector('[data-ba-map-list]');
					if (list) {
						const targetTop = Math.max(0, listCard.offsetTop - 12);
						list.scrollTo({ top: targetTop, behavior: 'smooth' });
					}
				}
			};
			entry.marker.addListener('click', select);
			entry.circle?.addListener('click', select);
			bounds.extend(entry.position);
		});

		if (context === 'full') {
			// MAP-UX-PHASE4: preserve the initial map/URL viewport, then fit to
			// visible PUBLIC event geometry on subsequent filter changes only.
			let initialFilterHandled = false;
			document.addEventListener('badaround:map-filtered', (event) => {
				const allowed = new Set((event.detail?.items || []).map((item) => String(item.id)));
				eventMarkers.forEach((entry) => {
					entry.filteredVisible = allowed.has(String(entry.item.id));
					showProjection(entry, entry.filteredVisible ? map : null);
				});
				if (selected && !selected.filteredVisible) clearSelected();
				removeClusters();
				refreshClusters();
				if (!initialFilterHandled) {
					initialFilterHandled = true;
					return;
				}
				const visible = eventMarkers.filter((entry) => entry.filteredVisible);
				if (!visible.length) return; // never jump to an arbitrary region on zero results
				const targetBounds = new google.maps.LatLngBounds();
				visible.forEach((entry) => {
					if (entry.circle && typeof entry.circle.getBounds === 'function') {
						const circleBounds = entry.circle.getBounds();
						if (circleBounds) { targetBounds.union(circleBounds); return; }
					}
					targetBounds.extend(entry.position);
				});
				if (visible.length === 1 && !visible[0].circle) {
					map.setCenter(visible[0].position);
					map.setZoom(14);
				} else {
					map.fitBounds(targetBounds, 72);
					google.maps.event.addListenerOnce(map, 'idle', () => {
						if (map.getZoom() > 15) map.setZoom(15);
					});
				}
			});
		}

		const removeClusters = () => {
			clusterMarkers.forEach((marker) => marker.setMap(null));
			clusterMarkers = [];
		};

		function refreshClusters() {
			if (!overlayReady || !eventMarkers.length) return;
			const projection = overlay.getProjection();
			if (!projection) return;

			removeClusters();
			const visibleMarkers = eventMarkers.filter((entry) => entry.filteredVisible !== false);
			eventMarkers.forEach((entry) => showProjection(entry, entry.filteredVisible !== false ? map : null));

			if (selected && selected.filteredVisible !== false) {
				showProjection(selected, map);
				selected.marker.setIcon(projectionIcon(selected, true));
				selected.marker.setZIndex(400);
			}

			if (map.getZoom() >= 17) return;

			const grid = 64;
			const groups = new Map();
			visibleMarkers.filter((entry) => entry !== selected).forEach((entry) => {
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

		map.addListener('idle', () => {
			refreshClusters();
			if (selected) positionPreview(selected);
		});

		if (eventMarkers.length === 1) {
			map.setCenter(bounds.getCenter());
			map.setZoom(13);
		} else if (eventMarkers.length > 1) {
			map.fitBounds(bounds, 48);
		} else {
			setStatus('Gli eventi pubblicati non dispongono ancora di una posizione pubblica cartografabile.');
		}

		if (context === 'full') {
			const requestedArea = initialMapArea();
			if (requestedArea?.bounds) {
				map.fitBounds(requestedArea.bounds, 64);
			} else if (requestedArea?.position) {
				map.setCenter(requestedArea.position);
				map.setZoom(14);
			}
			const searchField = document.querySelector('[data-ba-map-search]');
			if (requestedArea?.area && searchField) searchField.value = requestedArea.area;
		}

		if (context === 'home') {
            const showHomeArea = (event) => {
                const current = event.detail?.position;
                if (!current || !Number.isFinite(current.lat) || !Number.isFinite(current.lng)) return;
                map.panTo(current); map.setZoom(14);
                if (event.detail?.source === 'geolocation') {
                    if (!userMarker) userMarker = new google.maps.Marker({map,position:current,title:'La tua posizione attuale',icon:userDotSvg(),zIndex:400});
                    else { userMarker.setPosition(current); userMarker.setMap(map); }
                } else if (userMarker) userMarker.setMap(null);
            };
            document.addEventListener('badaround:home-area', showHomeArea);
            document.dispatchEvent(new CustomEvent('badaround:home-map-ready'));
        }

		window.addEventListener('resize', () => {
			if (selected) positionPreview(selected);
		}, { passive: true });

		if (context === 'full') {
			document.addEventListener('badaround:nearby-request', (event) => {
				const current = event.detail?.position;
				if (!current || !Number.isFinite(current.lat) || !Number.isFinite(current.lng)) return;
				if (!userMarker) {
					userMarker = new google.maps.Marker({
						map, position: current, title: 'La tua posizione attuale',
						icon: userDotSvg(), zIndex: 400,
					});
				} else {
					userMarker.setPosition(current); userMarker.setMap(map);
				}
				map.panTo(current);
				if (map.getZoom() < 13) map.setZoom(13);
			});
			document.addEventListener('badaround:place-selected', (event) => {
				const position = event.detail?.position;
				const viewport = event.detail?.viewport;
				if (viewport) {
					map.fitBounds(viewport, 64);
					return;
				}
				if (!position || !Number.isFinite(position.lat) || !Number.isFinite(position.lng)) return;
				map.panTo(position);
				if (map.getZoom() < 13) map.setZoom(13);
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

	const scheduleMaps = (run) => {
		const homeOnly = mapNodes.every((node) => (node.dataset.baMapContext || 'full') === 'home');
		// Progressive enhancement only: environments without idle scheduling keep
		// the certified immediate behavior, including test/legacy browsers.
		if (!homeOnly || !('requestIdleCallback' in window)) { run(); return; }
		const start = () => window.requestIdleCallback(run, { timeout: 1800 });
		if (document.readyState === 'complete') start();
		else window.addEventListener('load', start, { once: true });
	};

	const setupPlacesAutocomplete = async (input, onSelected, options = {}) => {
		if (!input || !window.google?.maps?.importLibrary) return;
        // Bind immediately; import Places only when the user starts using search.
        let AutocompleteSuggestion, AutocompleteSessionToken, placesPromise;
        const ensurePlaces = () => {
            if (!placesPromise) placesPromise = google.maps.importLibrary('places').then((places) => {
                ({ AutocompleteSuggestion, AutocompleteSessionToken } = places || {});
                if (!AutocompleteSuggestion || !AutocompleteSessionToken) throw new Error('Places unavailable');
                token = new AutocompleteSessionToken();
                return true;
            }).catch((error) => {
                placesPromise = null;
                console.warn('BadAround Places Autocomplete non disponibile:', error);
                return false;
            });
            return placesPromise;
        };

		const host = input.closest('.ba-map-inline-search') || input.parentElement;
		if (!host) return;
		host.classList.add('ba-map-search--autocomplete');

		const menu = document.createElement('div');
		menu.className = 'ba-map-place-suggestions';
		menu.hidden = true;
		menu.setAttribute('role','listbox');
		host.appendChild(menu);

		let token = null;
		let timer = null;
		let requestId = 0;

		const close = () => {
            ++requestId; // A cleared/closed search cannot show stale in-flight suggestions.
			menu.hidden = true;
			menu.replaceChildren();
		};

		const choose = async (suggestion) => {
			const prediction = suggestion?.placePrediction;
			if (!prediction) return;
			try {
				const place = prediction.toPlace();
				await place.fetchFields({ fields:['displayName','formattedAddress','location','viewport'] });
				input.value = place.formattedAddress || place.displayName || '';
				close();
				const location = place.location;
				const detail = {
					placeId: place.id || prediction.placeId || '',
					label: place.formattedAddress || place.displayName || '',
					position: location ? { lat:location.lat(), lng:location.lng() } : null,
					viewport: place.viewport || null,
				};
				if (typeof onSelected === 'function') onSelected(detail);
				token = new AutocompleteSessionToken();
				if (options.dispatch !== false) {
					document.dispatchEvent(new CustomEvent('badaround:place-selected', { detail }));
				}
			} catch (error) {
				console.warn('BadAround Places selection:', error);
			}
		};

		const predict = () => {
			clearTimeout(timer);
			const query = input.value.trim();
            const currentRequest = ++requestId;
			if (query.length < 2) { close(); return; }
			timer = setTimeout(async () => {
				try {
                    if (!await ensurePlaces() || currentRequest !== requestId) return;
					const response = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
						input: query,
						sessionToken: token,
						includedRegionCodes: ['it'],
					});
					if (currentRequest !== requestId) return;
					const suggestions = response?.suggestions || [];
					menu.replaceChildren();
					suggestions.slice(0,6).forEach((suggestion) => {
						const prediction = suggestion.placePrediction;
						if (!prediction) return;
						const button = document.createElement('button');
						button.type = 'button';
						button.className = 'ba-map-place-suggestion';
						button.setAttribute('role','option');
						const primary = prediction.mainText?.text || prediction.text?.text || '';
						const secondary = prediction.secondaryText?.text || '';
						const strong = document.createElement('strong');
						strong.textContent = primary;
						const small = document.createElement('small');
						small.textContent = secondary;
						button.append(strong,small);
						button.addEventListener('mousedown', (event) => event.preventDefault());
						button.addEventListener('click', () => choose(suggestion));
						menu.appendChild(button);
					});
					menu.hidden = !menu.childElementCount;
				} catch (error) {
					close();
					console.warn('BadAround Places predictions:', error);
				}
			},180);
		};
        input.addEventListener('input', predict);
        input.addEventListener('focus', () => { void ensurePlaces(); });
        if (document.activeElement === input) {
            void ensurePlaces();
            if (input.value.trim().length >= 2) predict();
        }
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') close();
		});
		document.addEventListener('click', (event) => {
			if (!host.contains(event.target)) close();
		});
	};

	const setupHomePlaceSearch = async () => {
		const form = document.querySelector('[data-ba-home-place-search]');
		const input = form?.querySelector('[data-ba-home-place-input]');
		if (!form || !input) return;

		const setValue = (selector, value) => {
			const field = form.querySelector(selector);
			if (field) field.value = value ?? '';
		};
		const clearResolvedPlace = () => {
			setValue('[data-ba-home-place-id]','');
			setValue('[data-ba-home-place-lat]','');
			setValue('[data-ba-home-place-lng]','');
			setValue('[data-ba-home-place-north]','');
			setValue('[data-ba-home-place-south]','');
			setValue('[data-ba-home-place-east]','');
			setValue('[data-ba-home-place-west]','');
		};

		await setupPlacesAutocomplete(input, (detail) => {
			clearResolvedPlace();
            document.dispatchEvent(new CustomEvent('badaround:home-area-selected', {detail}));
			setValue('[data-ba-home-place-id]', detail?.placeId || '');
			setValue('[data-ba-home-place-lat]', detail?.position?.lat ?? '');
			setValue('[data-ba-home-place-lng]', detail?.position?.lng ?? '');
			const viewport = detail?.viewport;
			if (viewport) {
				const ne = viewport.getNorthEast?.();
				const sw = viewport.getSouthWest?.();
				setValue('[data-ba-home-place-north]', ne?.lat?.() ?? '');
				setValue('[data-ba-home-place-east]', ne?.lng?.() ?? '');
				setValue('[data-ba-home-place-south]', sw?.lat?.() ?? '');
				setValue('[data-ba-home-place-west]', sw?.lng?.() ?? '');
			}
		}, { dispatch:false });

		input.addEventListener('input', clearResolvedPlace);
		form.addEventListener('submit', (event) => {
			const area = input.value.trim();
			if (!area) {
				event.preventDefault();
				input.focus();
			}
		});
	};

	const initialMapArea = () => {
		const params = new URLSearchParams(window.location.search);
		const area = params.get('area') || '';
		const lat = params.get('lat') === null ? NaN : Number(params.get('lat'));
		const lng = params.get('lng') === null ? NaN : Number(params.get('lng'));
		const north = params.get('north') === null ? NaN : Number(params.get('north'));
		const south = params.get('south') === null ? NaN : Number(params.get('south'));
		const east = params.get('east') === null ? NaN : Number(params.get('east'));
		const west = params.get('west') === null ? NaN : Number(params.get('west'));
		const hasPosition = Number.isFinite(lat) && Number.isFinite(lng);
		const hasBounds = [north,south,east,west].every(Number.isFinite);
		if (!area && !hasPosition && !hasBounds) return null;
		return {
			area,
			position: hasPosition ? { lat, lng } : null,
			bounds: hasBounds ? { north, south, east, west } : null,
		};
	};

	const load = async () => {
        // Detail geometry is already in the public DOM: avoid downloading the national catalog.
        if(mapNodes.every((node)=>(node.dataset.baMapContext||'full')==='detail')){
            let started=false;
            const button=document.querySelector('[data-ba-detail-map-load]'), status=document.querySelector('[data-ba-detail-map-status]');
            let observer;
            const start=async()=>{
                if(started)return;started=true;observer?.disconnect();
                if(button){button.disabled=true;button.textContent='Caricamento mappa…';}
                status?.classList.remove('ba-map-status--complete');
                try{
                    if(!await loadGoogleMaps())throw new Error('Maps unavailable');
                    mapNodes.forEach((node)=>initMap(node,[]));
                    if(button){button.hidden=true;button.textContent='Mappa caricata';}
                    if(status){status.textContent='Mappa della posizione pubblica caricata.';status.classList.add('ba-map-status--complete');}
                }catch(error){
                    started=false;googleMapsPromise=undefined;
                    document.querySelector('script[data-ba-google-maps]')?.remove();
                    if(button){button.disabled=false;button.textContent='Riprova a caricare la mappa';}
                    if(status)status.textContent='Mappa non disponibile. Indirizzo e dettagli restano consultabili.';
                }
            };
            button?.addEventListener('click',start);
            if('IntersectionObserver' in window){observer=new IntersectionObserver((entries)=>{if(entries.some((entry)=>entry.isIntersecting))start();},{rootMargin:'200px'});mapNodes.forEach((node)=>observer.observe(node));}
            return;
        }
		let payload;
		try {
			payload = await loadDiscovery();
		} catch (error) {
			console.error('BadAround map:', error);
			document.querySelectorAll('[data-ba-map-status]').forEach((el) => {
				el.textContent = 'Errore nel caricamento della mappa. Puoi continuare con la ricerca manuale.';
				el.hidden = false;
			});
			return;
		}

		const items = Array.isArray(payload.items) ? payload.items : [];
		let visibleItems = [...items];
		let activeCategory = 'all';
		let activeTime = 'all';
		let searchQuery = '';
		let nearbyPosition = null;
		const nearbyRadiusKm = 5;

		const parseEventDate = (value) => {
			const raw = String(value || '').trim();
			if (!raw) return null;
			let match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
			if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
			match = raw.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
			if (match) return new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
			const parsed = new Date(raw);
			return Number.isNaN(parsed.getTime()) ? null : new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
		};

		const haversineKm = (a, b) => {
			const toRad = (deg) => deg * Math.PI / 180;
			const dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
			const lat1 = toRad(a.lat), lat2 = toRad(b.lat);
			const h = Math.sin(dLat/2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng/2) ** 2;
			return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1-h));
		};

		const matchesTime = (item) => {
			if (activeTime === 'all') return true;
			const date = parseEventDate(item.occurred_date);
			if (!date) return false;
			const now = new Date();
			const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
			const diffDays = Math.floor((today - date) / 86400000);
			if (activeTime === 'today') return diffDays === 0;
			const days = Number(activeTime);
			return Number.isFinite(days) && diffDays >= 0 && diffDays < days;
		};

		const matchesNearby = (item) => {
			if (!nearbyPosition) return true;
			const geo = publicGeometry(item.public_geo);
			return geo ? haversineKm(nearbyPosition, geo.position) <= nearbyRadiusKm : false;
		};

		const applyFilters = () => {
			visibleItems = items.filter((item) => {
				const category = categoryFor(item);
				const matchesCategory = activeCategory === 'all' || category === activeCategory;
				const haystack = [
					item.title,
					item.excerpt,
					item.public_place_name,
					item.territory?.name,
					item.event_type?.name,
				].filter(Boolean).join(' ').toLowerCase();
				const matchesSearch = !searchQuery || haystack.includes(searchQuery);
				return matchesCategory && matchesSearch && matchesTime(item) && matchesNearby(item);
			});
			if (nearbyPosition) {
				visibleItems.sort((a,b) => {
					const ga=publicGeometry(a.public_geo), gb=publicGeometry(b.public_geo);
					return (ga ? haversineKm(nearbyPosition,ga.position) : Infinity) - (gb ? haversineKm(nearbyPosition,gb.position) : Infinity);
				});
			}
			renderList(visibleItems, visibleItems.length);
			document.dispatchEvent(new CustomEvent('badaround:map-filtered', { detail: { items: visibleItems } }));
		};

		document.querySelectorAll('[data-ba-map-filter]').forEach((button) => {
			button.addEventListener('click', () => {
				activeCategory = button.dataset.baMapFilter || 'all';
				document.querySelectorAll('[data-ba-map-filter]').forEach((el) => {
					const active = el === button;
					el.classList.toggle('is-active', active);
					el.setAttribute('aria-pressed', String(active));
				});
				applyFilters();
			});
		});

		document.querySelectorAll('[data-ba-map-time]').forEach((button) => {
			button.addEventListener('click', () => {
				activeTime = button.dataset.baMapTime || 'all';
				document.querySelectorAll('[data-ba-map-time]').forEach((el) => {
					const active = el === button;
					el.classList.toggle('is-active', active);
					el.setAttribute('aria-pressed', String(active));
				});
				applyFilters();
			});
		});

		const nearbyButton = document.querySelector('[data-ba-map-nearby]');
		const nearbyStatus = document.querySelector('[data-ba-map-nearby-status]');
		nearbyButton?.addEventListener('click', () => {
			if (nearbyPosition) {
				nearbyPosition = null;
				nearbyButton.setAttribute('aria-pressed','false');
				if (nearbyStatus) nearbyStatus.textContent = '';
				applyFilters();
				return;
			}
			if (!navigator.geolocation) {
				if (nearbyStatus) nearbyStatus.textContent = 'La geolocalizzazione non è disponibile nel browser.';
				return;
			}
			nearbyButton.disabled = true;
			if (nearbyStatus) nearbyStatus.textContent = 'Ricerca della tua posizione…';
			navigator.geolocation.getCurrentPosition(
				(position) => {
					nearbyPosition = { lat:Number(position.coords.latitude), lng:Number(position.coords.longitude) };
					nearbyButton.disabled = false;
					nearbyButton.setAttribute('aria-pressed','true');
					if (nearbyStatus) nearbyStatus.textContent = 'Mostro gli eventi entro 5 km. La tua posizione non viene salvata.';
					document.dispatchEvent(new CustomEvent('badaround:nearby-request',{detail:{position:nearbyPosition}}));
					applyFilters();
				},
				(error) => {
					nearbyButton.disabled = false;
					let message = 'Non è stato possibile determinare la posizione.';
					if (error.code === error.PERMISSION_DENIED) message = 'Permesso posizione non concesso.';
					else if (error.code === error.TIMEOUT) message = 'La richiesta della posizione è scaduta.';
					else if (error.code === error.POSITION_UNAVAILABLE) message = 'Posizione temporaneamente non disponibile.';
					if (nearbyStatus) nearbyStatus.textContent = message;
				},
				{ enableHighAccuracy:false, timeout:10000, maximumAge:60000 }
			);
		});

		const searchInput = document.querySelector('[data-ba-map-search]');
		const requestedArea = initialMapArea();
		if (requestedArea?.area && searchInput) searchInput.value = requestedArea.area;
		searchInput?.addEventListener('input', () => {
			searchQuery = searchInput.value.trim().toLowerCase();
			applyFilters();
		});

		const hasFullMap = mapNodes.some((node) => (node.dataset.baMapContext || 'full') === 'full');
		if (hasFullMap) {
			applyFilters();
		} else {
			renderList(visibleItems, visibleItems.length);
		}

		scheduleMaps(async () => {
			try { await loadGoogleMaps(); }
			catch (error) { console.error('BadAround map:', error); }
			mapNodes.forEach((node) => initMap(node, items));
			await setupHomePlaceSearch();
			if (hasFullMap) {
				await setupPlacesAutocomplete(searchInput, () => {
					searchQuery = '';
					applyFilters();
				});
				applyFilters();
			}
		});
	};

	bindResultsPanel();
	load();
})();
