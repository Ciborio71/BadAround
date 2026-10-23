(function (root) {
	'use strict';
	const mounted = new WeakMap();
	// Viewport policy only: never read or write report/location/projection state.
	function options() {
		const position = root.google.maps.ControlPosition.RIGHT_TOP;
		return {
			disableDefaultUI: false, cameraControl: false,
			zoomControl: true, zoomControlOptions: { position },
			fullscreenControl: true, fullscreenControlOptions: { position },
			draggable: true, keyboardShortcuts: true,
			gestureHandling: 'cooperative', disableDoubleClickZoom: false,
		};
	}
	function mount(map, canvas) {
		if (mounted.has(map)) return mounted.get(map);
		canvas.classList.add('ba-map-navigation-enabled');
		canvas.parentElement?.classList.add('ba-map-navigation-shell');
		const control = canvas.ownerDocument.createElement('div');
		control.className = 'ba-map-pan';
		control.setAttribute('role', 'group');
		control.setAttribute('aria-label', 'Sposta la mappa');
		// Compact 2×2 pad leaves room for native zoom/fullscreen on narrow maps.
		for (const [direction, symbol, x, y] of [
			['nord', '↑', 0, -1], ['sud', '↓', 0, 1],
			['ovest', '←', -1, 0], ['est', '→', 1, 0],
		]) {
			const button = canvas.ownerDocument.createElement('button');
			button.type = 'button';
			button.textContent = symbol;
			button.setAttribute('aria-label', 'Sposta mappa verso ' + direction);
			button.title = 'Sposta mappa verso ' + direction;
			button.addEventListener('click', () => {
				// A quarter of the current viewport, including after fullscreen/resize.
				map.panBy(x * Math.round(canvas.clientWidth / 4), y * Math.round(canvas.clientHeight / 4));
			});
			control.append(button);
		}
		control.index = 10;
		map.controls[root.google.maps.ControlPosition.RIGHT_TOP].push(control);
		mounted.set(map, control);
		return control;
	}
	root.BadAroundMapNavigation = { options, mount };
})(typeof window !== 'undefined' ? window : globalThis);
