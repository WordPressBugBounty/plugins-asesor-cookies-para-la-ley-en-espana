function cdpCookiesActivateTab(tab) {
	if (!tab) {
		return;
	}

	var target = tab.getAttribute('href');

	if (!target || target.charAt(0) !== '#') {
		return;
	}

	document.querySelectorAll('.cdp-cookies-tab').forEach(function(node) {
		node.classList.toggle('is-active', node === tab);
	});

	document.querySelectorAll('.cdp-cookies-card').forEach(function(section) {
		section.hidden = '#' + section.id !== target;
	});

	try {
		window.localStorage.setItem('cdpCookiesActiveTab', target);
	} catch (error) {}
}

document.addEventListener('click', function(event) {
	var editButton = event.target.closest('[data-cdp-edit-cookie]');

	if (editButton) {
		var editId = editButton.getAttribute('data-cdp-edit-cookie');
		var editRow = document.querySelector('[data-cdp-edit-cookie-row="' + editId + '"]');

		if (editRow) {
			editRow.hidden = !editRow.hidden;
		}

		return;
	}

	var cancelEditButton = event.target.closest('[data-cdp-cancel-edit-cookie]');

	if (cancelEditButton) {
		var cancelId = cancelEditButton.getAttribute('data-cdp-cancel-edit-cookie');
		var cancelRow = document.querySelector('[data-cdp-edit-cookie-row="' + cancelId + '"]');

		if (cancelRow) {
			cancelRow.hidden = true;
		}

		return;
	}

	var languageTab = event.target.closest('[data-cdp-banner-lang]');

	if (languageTab) {
		var language = languageTab.getAttribute('data-cdp-banner-lang');
		var wrapper = languageTab.closest('[data-cdp-banner-lang-tabs]');

		if (wrapper) {
			wrapper.querySelectorAll('[data-cdp-banner-lang]').forEach(function(node) {
				node.classList.toggle('is-active', node === languageTab);
			});
		}

		document.querySelectorAll('[data-cdp-banner-text]').forEach(function(textarea) {
			textarea.hidden = textarea.getAttribute('data-cdp-banner-text') !== language;
		});

		return;
	}

	var tab = event.target.closest('.cdp-cookies-tab');

	if (!tab) {
		tab = event.target.closest('.cdp-cookies-card a[href^="#cdp-cookies-"]');
	}

	if (!tab) {
		return;
	}

	event.preventDefault();
	cdpCookiesActivateTab(tab);

	if (history.replaceState) {
		history.replaceState(null, '', tab.getAttribute('href'));
	}
});

document.addEventListener('DOMContentLoaded', function() {
	var target = window.location.hash;

	if (!target) {
		try {
			target = window.localStorage.getItem('cdpCookiesActiveTab') || '';
		} catch (error) {}
	}

	var tab = target ? document.querySelector('.cdp-cookies-tab[href="' + target + '"]') : null;

	cdpCookiesActivateTab(tab || document.querySelector('.cdp-cookies-tab'));
});

document.addEventListener('change', function(event) {
	var select = event.target.closest('[data-cdp-cookie-preset]');

	if (!select || !select.value || !window.cdpCookiesAdmin || !window.cdpCookiesAdmin.presets) {
		return;
	}

	var preset = window.cdpCookiesAdmin.presets[select.value];

	if (!preset) {
		return;
	}

	var fields = {
		cookie_name: preset.name || '',
		cookie_provider: preset.provider || '',
		cookie_service_pattern: preset.service_pattern || '',
		cookie_category: preset.category || 'necessary',
		cookie_type: preset.type || 'own',
		cookie_duration: preset.duration || '',
		cookie_description: preset.description || ''
	};

	Object.keys(fields).forEach(function(id) {
		var field = document.getElementById(id);

		if (field) {
			field.value = fields[id];
		}
	});
});

document.addEventListener('submit', function(event) {
	var form = event.target.closest('[data-cdp-delete-cookie-form]');

	if (!form) {
		return;
	}

	event.preventDefault();

	if (window.cdpCookiesAdmin && window.cdpCookiesAdmin.deleteConfirm && !window.confirm(window.cdpCookiesAdmin.deleteConfirm)) {
		return;
	}

	var button = form.querySelector('button[type="submit"]');
	var cookieIdInput = form.querySelector('input[name="cookie_id"]');
	var body = new FormData();

	body.append('action', 'cdp_cookies_delete_cookie');
	body.append('nonce', window.cdpCookiesAdmin ? window.cdpCookiesAdmin.deleteNonce : '');
	body.append('cookie_id', cookieIdInput ? cookieIdInput.value : '');

	if (button) {
		button.disabled = true;
	}

	fetch(window.cdpCookiesAdmin.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: body
	}).then(function(response) {
		return response.json();
	}).then(function(payload) {
		if (!payload || !payload.success) {
			throw new Error(payload && payload.data && payload.data.message ? payload.data.message : '');
		}

		var row = form.closest('[data-cdp-cookie-row]');

		if (row) {
			row.remove();
		}
	}).catch(function(error) {
		window.alert(error.message || (window.cdpCookiesAdmin ? window.cdpCookiesAdmin.deleteError : 'Error'));

		if (button) {
			button.disabled = false;
		}
	});
});
