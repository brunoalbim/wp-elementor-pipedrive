(function () {
	'use strict';

	var COOKIE_NAME = 'epd_utm';
	var COOKIE_DAYS = 30;
	var UTM_KEYS    = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

	// -------------------------------------------------------------------------
	// Cookie helpers
	// -------------------------------------------------------------------------

	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var d = new Date();
			d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
			expires = '; expires=' + d.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
	}

	function getCookie(name) {
		var nameEQ = name + '=';
		var parts  = document.cookie.split(';');
		for (var i = 0; i < parts.length; i++) {
			var c = parts[i].trim();
			if (c.indexOf(nameEQ) === 0) {
				return decodeURIComponent(c.substring(nameEQ.length));
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// UTM capture from current URL
	// -------------------------------------------------------------------------

	function parseUtmsFromUrl() {
		var search = window.location.search.slice(1);
		var utms   = {};
		var found  = false;

		search.split('&').forEach(function (pair) {
			var idx = pair.indexOf('=');
			if (idx === -1) { return; }
			var key = decodeURIComponent(pair.slice(0, idx));
			var val = decodeURIComponent(pair.slice(idx + 1));
			if (UTM_KEYS.indexOf(key) !== -1 && val) {
				utms[key] = val;
				found = true;
			}
		});

		return found ? utms : null;
	}

	function loadUtmsFromCookie() {
		var raw = getCookie(COOKIE_NAME);
		if (!raw) { return null; }
		try {
			var data = JSON.parse(raw);
			return (typeof data === 'object' && data !== null) ? data : null;
		} catch (e) {
			return null;
		}
	}

	// -------------------------------------------------------------------------
	// Inject hidden fields into an Elementor form element
	// -------------------------------------------------------------------------

	function injectPageDataIntoForm(form) {
		var pageFields = [
			{ id: 'epd-page-url',   name: 'epd_page[page_url]',   value: window.location.href.split('?')[0] },
			{ id: 'epd-page-title', name: 'epd_page[page_title]', value: document.title },
		];
		pageFields.forEach(function (f) {
			if (form.querySelector('#' + f.id)) { return; }
			var input   = document.createElement('input');
			input.type  = 'hidden';
			input.id    = f.id;
			input.name  = f.name;
			input.value = f.value;
			form.appendChild(input);
		});
	}

	function injectUtmsIntoForm(form, utms) {
		UTM_KEYS.forEach(function (key) {
			var existingId = 'epd-' + key;

			// Evita duplicar se já injetou antes.
			if (form.querySelector('#' + existingId)) { return; }

			var input    = document.createElement('input');
			input.type   = 'hidden';
			input.id     = existingId;
			input.name   = 'epd_utm[' + key + ']';
			input.value  = utms[key] || '';
			form.appendChild(input);
		});
	}

	function injectIntoForm(form, utms) {
		injectPageDataIntoForm(form);
		if (utms) {
			injectUtmsIntoForm(form, utms);
		}
	}

	function injectIntoAllForms(utms) {
		// Elementor Pro renderiza os forms como <form class="elementor-form">
		var forms = document.querySelectorAll('form.elementor-form');
		forms.forEach(function (form) {
			injectIntoForm(form, utms);
		});
	}

	// -------------------------------------------------------------------------
	// Observa forms carregados dinamicamente (popups, tabs, etc.)
	// -------------------------------------------------------------------------

	function observeDynamicForms(utms) {
		if (typeof MutationObserver === 'undefined') { return; }

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) { return; }

					// O próprio nó é um form Elementor.
					if (node.matches && node.matches('form.elementor-form')) {
						injectIntoForm(node, utms);
					}

					// Ou contém forms Elementor dentro dele (popup, etc.)
					var nested = node.querySelectorAll ? node.querySelectorAll('form.elementor-form') : [];
					nested.forEach(function (form) {
						injectIntoForm(form, utms);
					});
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	function init() {
		// 1. Tenta capturar UTMs da URL atual.
		var fromUrl = parseUtmsFromUrl();

		if (fromUrl) {
			// Encontrou UTMs na URL: salva/atualiza o cookie.
			setCookie(COOKIE_NAME, JSON.stringify(fromUrl), COOKIE_DAYS);
		}

		// 2. Resolve os UTMs a usar: URL tem prioridade, senão usa o cookie.
		var utms = fromUrl || loadUtmsFromCookie();

		// 3. Injeta nos forms já presentes no DOM.
		// Dados de página são sempre injetados; UTMs apenas se disponíveis.
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				injectIntoAllForms(utms);
				observeDynamicForms(utms);
			});
		} else {
			injectIntoAllForms(utms);
			observeDynamicForms(utms);
		}
	}

	init();
}());
