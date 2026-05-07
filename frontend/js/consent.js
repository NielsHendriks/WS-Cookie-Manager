(function () {
	'use strict';

	var COOKIE_NAME = 'wscm_consent';
	var config = window.wscm_config || {};
	var expiryDays = config.expiry_days || 365;

	function getConsent() {
		try {
			var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE_NAME + '=([^;]+)'));
			return match ? JSON.parse(decodeURIComponent(match[1])) : null;
		} catch (e) {
			return null;
		}
	}

	function setConsent(consent, actionType) {
		var expires = new Date();
		expires.setDate(expires.getDate() + expiryDays);
		var value = encodeURIComponent(JSON.stringify(consent));
		var cookieStr = COOKIE_NAME + '=' + value + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
		if (location.protocol === 'https:') {
			cookieStr += '; Secure';
		}
		document.cookie = cookieStr;

		window.__wscm_consent = consent;
		activateScripts(consent);
		hideBanner();
		showReopen();

		logConsentToServer(consent, actionType || 'custom');
		document.dispatchEvent(new CustomEvent('wscm:consent', { detail: consent }));
	}

	function logConsentToServer(consent, actionType) {
		var restUrl = config.rest_url;
		if (!restUrl) return;

		var xhr = new XMLHttpRequest();
		xhr.open('POST', restUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.send(JSON.stringify({
			consent: consent,
			action_type: actionType,
			page_url: window.location.href
		}));
	}

	function activateScripts(consent) {
		// Activate blocked <script> tags
		var scripts = document.querySelectorAll('script[type="text/plain"][data-wscm-category]');
		for (var i = 0; i < scripts.length; i++) {
			var cat = scripts[i].getAttribute('data-wscm-category');
			if (consent[cat]) {
				var el = scripts[i];
				var newScript = document.createElement('script');

				for (var j = 0; j < el.attributes.length; j++) {
					var attr = el.attributes[j];
					if (attr.name === 'type' || attr.name === 'data-wscm-category') continue;
					newScript.setAttribute(attr.name, attr.value);
				}

				if (el.src) {
					newScript.src = el.src;
				} else {
					newScript.textContent = el.textContent;
				}

				el.parentNode.replaceChild(newScript, el);
			}
		}

		// Activate blocked <iframe> tags
		var iframes = document.querySelectorAll('iframe[data-wscm-category][data-wscm-src]');
		for (var k = 0; k < iframes.length; k++) {
			var icat = iframes[k].getAttribute('data-wscm-category');
			if (consent[icat]) {
				iframes[k].src = iframes[k].getAttribute('data-wscm-src');
				iframes[k].removeAttribute('data-wscm-src');
				iframes[k].removeAttribute('data-wscm-category');
			}
		}

		// Activate blocked <img> tags (tracking pixels)
		var imgs = document.querySelectorAll('img[data-wscm-category][data-wscm-src]');
		for (var m = 0; m < imgs.length; m++) {
			var mcat = imgs[m].getAttribute('data-wscm-category');
			if (consent[mcat]) {
				imgs[m].src = imgs[m].getAttribute('data-wscm-src');
				imgs[m].removeAttribute('data-wscm-src');
				imgs[m].removeAttribute('data-wscm-category');
			}
		}
	}

	function showBanner() {
		var banner = document.getElementById('wscm-banner');
		var overlay = document.getElementById('wscm-overlay');
		if (banner) {
			banner.style.display = '';
			banner.classList.add('wscm-visible');
		}
		if (overlay && config.position === 'center') {
			overlay.style.display = '';
		}
	}

	function hideBanner() {
		var banner = document.getElementById('wscm-banner');
		var overlay = document.getElementById('wscm-overlay');
		if (banner) {
			banner.classList.remove('wscm-visible');
			banner.classList.add('wscm-hiding');
			setTimeout(function () {
				banner.style.display = 'none';
				banner.classList.remove('wscm-hiding');
			}, 300);
		}
		if (overlay) {
			overlay.style.display = 'none';
		}
	}

	function showReopen() {
		var btn = document.getElementById('wscm-reopen');
		if (btn) btn.style.display = '';
	}

	function hideReopen() {
		var btn = document.getElementById('wscm-reopen');
		if (btn) btn.style.display = 'none';
	}

	function buildConsentObj(categories, accepted) {
		var consent = { necessary: true };
		for (var i = 0; i < categories.length; i++) {
			if (categories[i] !== 'necessary') {
				consent[categories[i]] = accepted;
			}
		}
		return consent;
	}

	function resetBannerState() {
		var banner = document.getElementById('wscm-banner');
		var categoriesEl = document.getElementById('wscm-categories');
		var manageBtn = document.getElementById('wscm-manage-btn');
		var saveBtn = document.getElementById('wscm-save-btn');
		var closeBtn = document.getElementById('wscm-close-btn');
		if (categoriesEl) categoriesEl.style.display = 'none';
		if (manageBtn) manageBtn.style.display = '';
		if (saveBtn) saveBtn.style.display = 'none';
		if (closeBtn) closeBtn.style.display = 'none';
		if (banner) banner.classList.remove('wscm-prefs-open');
	}

	function init() {
		var consent = getConsent();

		if (consent) {
			try { activateScripts(consent); } catch (e) { /* ensure bindEvents always runs */ }
			showReopen();
		} else {
			showBanner();
		}

		bindEvents();
	}

	function bindEvents() {
		var acceptBtn = document.getElementById('wscm-accept-btn');
		var rejectBtn = document.getElementById('wscm-reject-btn');
		var manageBtn = document.getElementById('wscm-manage-btn');
		var saveBtn = document.getElementById('wscm-save-btn');
		var reopenBtn = document.getElementById('wscm-reopen');
		var closeBtn = document.getElementById('wscm-close-btn');
		var categoriesEl = document.getElementById('wscm-categories');
		var categories = config.categories || [];

		if (acceptBtn) {
			acceptBtn.addEventListener('click', function () {
				setConsent(buildConsentObj(categories, true), 'accept_all');
			});
		}

		if (rejectBtn) {
			rejectBtn.addEventListener('click', function () {
				setConsent(buildConsentObj(categories, false), 'reject_all');
			});
		}

		if (manageBtn) {
			manageBtn.addEventListener('click', function () {
				var banner = document.getElementById('wscm-banner');
				if (categoriesEl) {
					var isVisible = categoriesEl.style.display !== 'none';
					categoriesEl.style.display = isVisible ? 'none' : '';
					manageBtn.style.display = isVisible ? '' : 'none';
					if (saveBtn) saveBtn.style.display = isVisible ? 'none' : '';
					if (banner) banner.classList.toggle('wscm-prefs-open', !isVisible);
				}
			});
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', function () {
				var consent = { necessary: true };
				var toggles = document.querySelectorAll('.wscm-cat-toggle');
				for (var i = 0; i < toggles.length; i++) {
					consent[toggles[i].value] = toggles[i].checked;
				}
				setConsent(consent, 'save_preferences');
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				hideBanner();
				showReopen();
				resetBannerState();
			});
		}

		if (reopenBtn) {
			reopenBtn.addEventListener('click', function () {
				hideReopen();

				var existingConsent = getConsent();
				if (existingConsent) {
					var toggles = document.querySelectorAll('.wscm-cat-toggle');
					for (var i = 0; i < toggles.length; i++) {
						toggles[i].checked = !!existingConsent[toggles[i].value];
					}
				}

				if (categoriesEl) categoriesEl.style.display = '';
				var manageBtn2 = document.getElementById('wscm-manage-btn');
				if (manageBtn2) manageBtn2.style.display = 'none';
				if (saveBtn) saveBtn.style.display = '';
				if (closeBtn) closeBtn.style.display = '';
				var banner = document.getElementById('wscm-banner');
				if (banner) banner.classList.add('wscm-prefs-open');
				showBanner();
			});
		}
	}

	function initDarkMode() {
		var els = document.querySelectorAll('[data-wscm-dark="auto"]');
		if (!els.length) return;

		function apply(dark) {
			for (var i = 0; i < els.length; i++) {
				if (dark) {
					els[i].classList.add('wscm-dark');
				} else {
					els[i].classList.remove('wscm-dark');
				}
			}
		}

		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		apply(mq.matches);
		mq.addEventListener('change', function (e) { apply(e.matches); });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); initDarkMode(); });
	} else {
		init();
		initDarkMode();
	}
})();
