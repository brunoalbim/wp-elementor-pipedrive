(function () {
	'use strict';

	var COOKIE_NAME = 'epd_attribution';
	var LEGACY_COOKIE_NAME = 'epd_utm';
	var CARRIER_NAME = 'epd_attribution';
	var classifier = window.EPDAttributionClassifier;
	var config = window.epdAttribution || {};
	var COOKIE_DAYS = Number(config.cookieDays) || 90;

	if (!classifier) { return; }

	function readCookie(name) {
		var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		var match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
		if (!match) { return null; }
		try { return JSON.parse(decodeURIComponent(match[1])) || {}; } catch (error) { return null; }
	}

	function cleanText(value, maxLength) {
		return String(value || '').replace(/[\u0000-\u001F\u007F]/g, '').slice(0, maxLength);
	}

	function compactTouch(touch) {
		var compact = {};
		classifier.UTM_KEYS.concat(classifier.CLICK_IDS, ['normalized_source', 'utm_channel', 'attribution_method', 'non_direct']).forEach(function (key) {
			if (typeof touch[key] === 'boolean') {
				compact[key] = touch[key];
			} else if (touch[key]) {
				compact[key] = cleanText(touch[key], classifier.CLICK_IDS.indexOf(key) >= 0 ? 255 : 300);
			}
		});
		return compact;
	}

	function compactAttribution(data) {
		var compact = {
			first: compactTouch(data.first || {}),
			last: compactTouch(data.last || {}),
			landing_url: cleanText(data.landing_url, 1000),
			landing_referrer: cleanText(data.landing_referrer, 1000),
			expires_at: Number(data.expires_at) || (Date.now() + COOKIE_DAYS * 86400000)
		};
		return fitCookieBudget(compact);
	}

	function encodedSize(data) {
		return encodeURIComponent(JSON.stringify(data)).length;
	}

	function trimTouch(touch, textLength, clickLength) {
		classifier.UTM_KEYS.concat(['normalized_source', 'utm_channel', 'attribution_method']).forEach(function (key) {
			if (touch[key]) { touch[key] = cleanText(touch[key], textLength); }
		});
		classifier.CLICK_IDS.forEach(function (key) {
			if (touch[key]) { touch[key] = cleanText(touch[key], clickLength); }
		});
	}

	function fitCookieBudget(data) {
		if (encodedSize(data) <= 3600) { return data; }
		data.landing_url = cleanText(data.landing_url, 300);
		data.landing_referrer = cleanText(data.landing_referrer, 300);
		trimTouch(data.first, 140, 180);
		trimTouch(data.last, 140, 180);
		if (encodedSize(data) <= 3600) { return data; }

		classifier.CLICK_IDS.forEach(function (key) {
			if (data.first[key] && data.first[key] === data.last[key]) { delete data.first[key]; }
		});
		if (encodedSize(data) <= 3600) { return data; }

		data.landing_referrer = '';
		trimTouch(data.first, 80, 120);
		trimTouch(data.last, 80, 120);
		['utm_content', 'utm_term'].concat(classifier.CLICK_IDS).some(function (key) {
			if (encodedSize(data) <= 3600) { return true; }
			delete data.first[key];
			return false;
		});
		['utm_content', 'utm_term'].concat(classifier.CLICK_IDS).some(function (key) {
			if (encodedSize(data) <= 3600) { return true; }
			delete data.last[key];
			return false;
		});
		['landing_url', 'utm_campaign', 'normalized_source', 'utm_channel', 'attribution_method'].some(function (key) {
			if (encodedSize(data) <= 3600) { return true; }
			if (key === 'landing_url') {
				data.landing_url = '';
			} else {
				delete data.first[key];
				delete data.last[key];
			}
			return false;
		});
		if (encodedSize(data) > 3600) {
			trimTouch(data.first, 40, 80);
			trimTouch(data.last, 40, 80);
		}
		return data;
	}

	function writeCookie(name, value, expiresAt) {
		var secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value)) + '; expires=' + new Date(expiresAt).toUTCString() + '; path=/; SameSite=Lax' + secure;
	}

	function legacyAttribution() {
		var legacy = readCookie(LEGACY_COOKIE_NAME);
		if (!legacy || typeof legacy !== 'object' || Array.isArray(legacy)) { return null; }
		legacy.attribution_method = 'legacy_utm';
		legacy.non_direct = legacy.utm_source !== '(direct)';
		return { first: legacy, last: legacy };
	}

	function canonicalUtms(data) {
		var last = data.last || data.first || {};
		var utms = {};
		classifier.UTM_KEYS.forEach(function (key) { utms[key] = last[key] || ''; });
		return utms;
	}

	function resolveAttribution() {
		var now = Date.now();
		var stored = readCookie(COOKIE_NAME) || legacyAttribution() || {};
		if (stored.expires_at && Number(stored.expires_at) <= now) { stored = {}; }
		if (!stored.expires_at) { stored.expires_at = now + COOKIE_DAYS * 86400000; }

		var current = classifier.classify({
			search: window.location.search,
			referrer: document.referrer || '',
			currentHost: window.location.hostname,
			siteHost: config.siteHost || '',
			sources: config.sources || {}
		});

		if (!stored.landing_url) {
			stored.landing_url = window.location.href;
			stored.landing_referrer = document.referrer || '';
		}

		stored = compactAttribution(classifier.merge(stored, current, config.sources || {}));
		writeCookie(COOKIE_NAME, stored, stored.expires_at);
		writeCookie(LEGACY_COOKIE_NAME, canonicalUtms(stored), stored.expires_at);
		return stored;
	}

	function payload(data) {
		return Object.assign({}, data, {
			submission_url: window.location.href,
			submission_title: document.title || ''
		});
	}

	function setInput(form, name, value) {
		var input = form.querySelector('input[name="' + name + '"]');
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}
		input.value = value == null ? '' : String(value);
	}

	function inject(form, data) {
		if (!form || !form.querySelector) { return; }
		var utms = canonicalUtms(data);
		setInput(form, CARRIER_NAME, JSON.stringify(payload(data)));
		classifier.UTM_KEYS.forEach(function (key) {
			setInput(form, 'epd_utm[' + key + ']', utms[key]);
		});
		setInput(form, 'epd_page[page_url]', window.location.href.split('?')[0]);
		setInput(form, 'epd_page[page_title]', document.title || '');
	}

	function scan(data, root) {
		var scope = root && root.querySelectorAll ? root : document;
		if (scope.matches && scope.matches('form.elementor-form')) { inject(scope, data); }
		scope.querySelectorAll('form.elementor-form').forEach(function (form) { inject(form, data); });
	}

	function init() {
		var data = resolveAttribution();
		scan(data, document);

		document.addEventListener('submit', function (event) {
			if (event.target && event.target.matches && event.target.matches('form.elementor-form')) {
				inject(event.target, data);
			}
		}, true);

		if (window.MutationObserver && document.body) {
			new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) { scan(data, node); }
					});
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
