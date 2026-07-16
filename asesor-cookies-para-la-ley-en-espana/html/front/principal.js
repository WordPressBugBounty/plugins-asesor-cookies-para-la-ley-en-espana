(function() {
	'use strict';

	var config = window.cdpCookiesConfig || {};
	var cookieName = config.cookieName || 'cdp_cookie_consent';
	var maxAge = parseInt(config.maxAge || 31536000, 10);
	var scriptRuns = {};

	function readConsent() {
		var cookies = document.cookie ? document.cookie.split('; ') : [];

		for (var i = 0; i < cookies.length; i++) {
			var parts = cookies[i].split('=');
			var name = decodeURIComponent(parts.shift());

			if (name !== cookieName) {
				continue;
			}

			try {
				return JSON.parse(decodeURIComponent(parts.join('=')));
			} catch (error) {
				return null;
			}
		}

		return null;
	}

	function writeConsent(consent) {
		consent.updatedAt = new Date().toISOString();
		document.cookie = encodeURIComponent(cookieName) + '=' + encodeURIComponent(JSON.stringify(consent)) + '; max-age=' + maxAge + '; path=/; SameSite=Lax';
	}

	function buildConsent(accepted) {
		var consent = {
			necessary: true,
			analytics: !!accepted,
			marketing: !!accepted,
			personalization: !!accepted
		};

		return consent;
	}

	function syncPanel(consent) {
		var current = consent || readConsent() || buildConsent(false);

		document.querySelectorAll('[data-cdp-cookies-category]').forEach(function(input) {
			var category = input.getAttribute('data-cdp-cookies-category');

			if (!category || category === 'necessary') {
				input.checked = true;
				return;
			}

			input.checked = !!current[category];
		});
	}

	function executeScripts(consent) {
		var scripts = config.scripts || {};

		Object.keys(scripts).forEach(function(category) {
			if (!consent[category] || !scripts[category] || scriptRuns[category]) {
				return;
			}

			scriptRuns[category] = true;
			executeHtml(scripts[category]);
		});

		applyProtectedEmbeds(consent);
	}

	function executeHtml(html) {
		var template = document.createElement('template');
		template.innerHTML = html;

		appendFragmentWithExecutableScripts(document.body, template.content);
	}

	function appendFragmentWithExecutableScripts(parent, fragment) {
		var token = 'cdp' + String(Date.now()) + String(Math.random()).replace(/\D/g, '');

		Array.prototype.slice.call(fragment.querySelectorAll('script')).forEach(function(script) {
			script.setAttribute('data-cdp-cookies-script-token', token);
		});

		parent.appendChild(fragment);
		activateScripts(parent, token);
	}

	function activateScripts(root, token) {
		var selector = token ? 'script[data-cdp-cookies-script-token="' + token + '"]' : 'script';

		Array.prototype.slice.call(root.querySelectorAll(selector)).forEach(function(oldScript) {
			var newScript = document.createElement('script');

			Array.prototype.slice.call(oldScript.attributes).forEach(function(attribute) {
				if (attribute.name !== 'data-cdp-cookies-script-token') {
					newScript.setAttribute(attribute.name, attribute.value);
				}
			});

			newScript.text = oldScript.text || oldScript.textContent || '';
			oldScript.parentNode.replaceChild(newScript, oldScript);
		});
	}

	function showBanner() {
		var banner = document.querySelector('[data-cdp-cookies-banner]');
		var preferences = document.querySelector('[data-cdp-cookies-open-preferences]');

		if (banner) {
			banner.hidden = false;
		}

		if (preferences) {
			preferences.hidden = true;
		}

		syncPanel(readConsent());
	}

	function hideBanner() {
		var banner = document.querySelector('[data-cdp-cookies-banner]');
		var preferences = document.querySelector('[data-cdp-cookies-open-preferences]');

		if (banner) {
			banner.hidden = true;
		}

		if (preferences) {
			preferences.hidden = false;
		}
	}

	function reloadPage() {
		window.location.reload();
	}

	function openPanel() {
		var panel = document.querySelector('[data-cdp-cookies-panel]');
		syncPanel(readConsent());

		if (panel) {
			panel.hidden = false;
		}
	}

	function closePanel() {
		var panel = document.querySelector('[data-cdp-cookies-panel]');
		if (panel) {
			panel.hidden = true;
		}
	}

	function saveCustomConsent() {
		var consent = buildConsent(false);

		document.querySelectorAll('[data-cdp-cookies-category]').forEach(function(input) {
			consent[input.getAttribute('data-cdp-cookies-category')] = input.checked;
		});

		writeConsent(consent);
		executeScripts(consent);
		hideBanner();
		syncPanel(consent);
	}

	function applyProtectedEmbeds(consent) {
		document.querySelectorAll('[data-cdp-consent-embed]').forEach(function(wrapper) {
			var category = wrapper.getAttribute('data-cdp-consent-category');

			if (!category || !consent || !consent[category] || wrapper.getAttribute('data-cdp-consent-loaded') === '1') {
				return;
			}

			var template = wrapper.querySelector('[data-cdp-consent-template]');
			var placeholder = wrapper.querySelector('[data-cdp-consent-placeholder]');

			if (!template) {
				return;
			}

			var content = template.content.cloneNode(true);

			if (placeholder) {
				placeholder.remove();
			}

			appendFragmentWithExecutableScripts(wrapper, content);
			wrapper.setAttribute('data-cdp-consent-loaded', '1');
		});
	}

	function acceptEmbedCategory(category) {
		var consent = readConsent() || buildConsent(false);
		consent.necessary = true;
		consent[category] = true;
		writeConsent(consent);
		executeScripts(consent);
		hideBanner();
	}

	document.addEventListener('click', function(event) {
		var embedButton = event.target.closest('[data-cdp-consent-embed-accept]');
		var preferencesLink = event.target.closest('a[href]');
		var opensPreferences = preferencesLink &&
			preferencesLink.hash === '#cdp-cookies-preferences' &&
			preferencesLink.origin === window.location.origin;

		if (embedButton) {
			acceptEmbedCategory(embedButton.getAttribute('data-cdp-consent-embed-accept'));
			return;
		}

		if (event.target.closest('[data-cdp-cookies-accept-all]')) {
			var accepted = buildConsent(true);
			writeConsent(accepted);
			executeScripts(accepted);
			hideBanner();
			syncPanel(accepted);
			return;
		}

		if (event.target.closest('[data-cdp-cookies-reject]')) {
			var rejected = buildConsent(false);
			writeConsent(rejected);
			hideBanner();
			syncPanel(rejected);
			reloadPage();
			return;
		}

		if (event.target.closest('[data-cdp-cookies-configure]') || event.target.closest('[data-cdp-cookies-open-preferences]') || opensPreferences) {
			event.preventDefault();
			showBanner();
			openPanel();
			return;
		}

		if (event.target.closest('[data-cdp-cookies-close]')) {
			closePanel();
			return;
		}

		if (event.target.closest('[data-cdp-cookies-save]')) {
			saveCustomConsent();
		}
	});

	document.addEventListener('DOMContentLoaded', function() {
		var consent = readConsent();

		if (consent) {
			hideBanner();
			syncPanel(consent);
			executeScripts(consent);
		} else {
			showBanner();
		}

		if (window.location.hash === '#cdp-cookies-preferences') {
			showBanner();
			openPanel();
		}

		runAudit();
	});

	function runAudit() {
		var audit = window.cdpCookiesAudit || {};

		if (!audit.enabled || !audit.ajaxUrl || !audit.nonce) {
			return;
		}

		sendAuditSnapshot();

		if (window.MutationObserver) {
			var timeout = null;
			var observer = new MutationObserver(function() {
				clearTimeout(timeout);
				timeout = setTimeout(sendAuditSnapshot, 800);
			});

			observer.observe(document.documentElement, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['src', 'href', 'data', 'srcset']
			});
		}

		setTimeout(sendAuditSnapshot, 2500);
	}

	function sendAuditSnapshot() {
		var audit = window.cdpCookiesAudit || {};
		var declared = Array.isArray(audit.declaredNames) ? audit.declaredNames : [];
		var names = getVisibleCookieNames(declared, audit.consentCookie);
		var resources = getExternalResources(audit.siteHost);
		var signature = JSON.stringify({
			cookies: names,
			resources: resources.map(function(resource) {
				return resource.url + '|' + resource.type;
			})
		});
		var throttleKey = 'cdpCookiesAudit:' + location.pathname + ':' + signature;

		try {
			if (sessionStorage.getItem(throttleKey)) {
				return;
			}
			sessionStorage.setItem(throttleKey, '1');
		} catch (error) {}

		if (!names.length && !resources.length) {
			return;
		}

		var body = new FormData();
		body.append('action', 'cdp_cookies_audit_detected');
		body.append('nonce', audit.nonce);
		body.append('url', location.href);
		names.forEach(function(name) {
			body.append('cookie_names[]', name);
		});
		resources.forEach(function(resource, index) {
			body.append('external_resources[' + index + '][url]', resource.url);
			body.append('external_resources[' + index + '][type]', resource.type);
		});

		fetch(audit.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).catch(function() {});
	}

	function getVisibleCookieNames(declared, consentCookie) {
		if (!document.cookie) {
			return [];
		}

		return document.cookie.split(';').map(function(part) {
			return decodeURIComponent(part.split('=')[0].trim());
		}).filter(function(name, index, all) {
			return name && all.indexOf(name) === index && declared.indexOf(name) === -1 && name !== consentCookie;
		});
	}

	function getExternalResources(siteHost) {
		var resources = [];
		var seen = {};

		function add(url, type) {
			var absolute = normalizeResourceUrl(url);
			var host = getUrlHost(absolute);
			var key = absolute + '|' + type;

			if (!absolute || !host || isSameHost(host, siteHost) || seen[key]) {
				return;
			}

			seen[key] = true;
			resources.push({
				url: absolute,
				type: type || 'resource'
			});
		}

		document.querySelectorAll('script[src], iframe[src], img[src], link[href], embed[src], object[data], video[src], audio[src], source[src]').forEach(function(element) {
			var url = element.getAttribute('src') || element.getAttribute('href') || element.getAttribute('data');
			add(url, element.tagName.toLowerCase());
		});

		if (window.performance && typeof window.performance.getEntriesByType === 'function') {
			window.performance.getEntriesByType('resource').forEach(function(entry) {
				add(entry.name, entry.initiatorType || 'resource');
			});
		}

		return resources.slice(0, 150);
	}

	function normalizeResourceUrl(url) {
		if (!url || typeof url !== 'string') {
			return '';
		}

		try {
			return new URL(url, location.href).href;
		} catch (error) {
			return '';
		}
	}

	function getUrlHost(url) {
		try {
			return new URL(url).host.toLowerCase();
		} catch (error) {
			return '';
		}
	}

	function isSameHost(host, siteHost) {
		var currentHost = location.host.toLowerCase();
		var configuredHost = siteHost ? String(siteHost).toLowerCase() : currentHost;

		return host === currentHost || host === configuredHost;
	}
})();
