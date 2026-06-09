(function () {
	var banner = document.getElementById("m4w-cc-banner");
	var modal = document.getElementById("m4w-cc-modal");
	if (!banner) return;

	var wpConsentMap = {
		functional: ["preferences"],
		analytics: ["statistics", "statistics-anonymous"],
		advertisement: ["marketing"]
	};

	function getCookie(name) {
		var escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
		var match = document.cookie.match(new RegExp("(?:^|; )" + escapedName + "=([^;]*)"));
		return match ? decodeURIComponent(match[1]) : "";
	}

	function setCookie(value, days) {
		var expiry = new Date(Date.now() + days * 864e5).toUTCString();
		document.cookie = _m4wCC.cookie + "=" + encodeURIComponent(value) + "; expires=" + expiry + "; path=/; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
	}

	function generateId() {
		return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
			var r = (Math.random() * 16) | 0;
			return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
		});
	}

	function getExistingConsent() {
		var raw = getCookie(_m4wCC.cookie);
		if (raw) return parseCookie(raw);

		raw = getCookie(_m4wCC.oldCookie);
		if (raw) return parseCookie(raw);

		return null;
	}

	function parseCookie(raw) {
		var data = {};
		raw.split(",").forEach(function (pair) {
			var parts = pair.split(":");
			if (parts[1]) data[parts[0]] = parts[1];
		});
		return data;
	}

	function buildCookieValue(data) {
		return Object.keys(data)
			.map(function (k) { return k + ":" + data[k]; })
			.join(",");
	}

	function isFullConsent(data) {
		return ["functional", "analytics", "advertisement"].every(function (cat) {
			return data[cat] === "yes";
		});
	}

	function saveConsent(data) {
		data.consentid = data.consentid || generateId();
		data.action = "yes";
		var value = buildCookieValue(data);
		var days = isFullConsent(data) ? _m4wCC.expiry : _m4wCC.expiryRejected;
		setCookie(value, days);
	}

	function updateGcm(data) {
		if (!_m4wCC.gcm || typeof gtag !== "function") return;
		var map = _m4wCC.gcmMap;
		var consentData = {};
		["necessary", "functional", "analytics", "advertisement"].forEach(function (slug) {
			if (!map[slug]) return;
			var granted = data[slug] === "yes";
			var types = map[slug][granted ? "granted" : "denied"];
			types.forEach(function (t) { consentData[t] = granted ? "granted" : "denied"; });
		});
		if (Object.keys(consentData).length) {
			gtag("consent", "update", consentData);
		}
	}

	function applyWpConsent(data) {
		if (typeof wp_set_consent !== "function") return;
		Object.keys(wpConsentMap).forEach(function (cat) {
			var status = data[cat] === "yes" ? "allow" : "deny";
			wpConsentMap[cat].forEach(function (type) {
				wp_set_consent(type, status);
			});
		});
	}

	function activateScripts(data) {
		document.querySelectorAll("script[data-m4w-cc-category]").forEach(function (old) {
			if (old.getAttribute("data-m4w-cc-active") === "1") return;
			var cat = old.getAttribute("data-m4w-cc-category");
			if (!cat || data[cat] !== "yes") return;
			var script = document.createElement("script");
			if (old.src) script.src = old.src;
			else script.textContent = old.textContent;
			Array.from(old.attributes).forEach(function (attr) {
				if (attr.name !== "type" && attr.name !== "data-m4w-cc-category") {
					script.setAttribute(attr.name, attr.value);
				}
			});
			script.setAttribute("data-m4w-cc-active", "1");
			old.parentNode.replaceChild(script, old);
		});
	}

	function fireConsentEvent(data) {
		var event = new CustomEvent("m4w_cc_consent_update", { detail: data });
		document.dispatchEvent(event);
	}

	function acceptAll(data) {
		data.consent = "yes";
		["necessary", "functional", "analytics", "advertisement"].forEach(function (cat) {
			data[cat] = "yes";
		});
		saveConsent(data);
		activateScripts(data);
		updateGcm(data);
		applyWpConsent(data);
		fireConsentEvent(data);
		hideBanner();
	}

	function rejectAll(data) {
		data.consent = "no";
		["necessary", "functional", "analytics", "advertisement"].forEach(function (cat) {
			data[cat] = cat === "necessary" ? "yes" : "no";
		});
		saveConsent(data);
		activateScripts(data);
		updateGcm(data);
		applyWpConsent(data);
		fireConsentEvent(data);
		hideBanner();
	}

	function saveCustom(data) {
		data.consent = "yes";
		var checkboxes = document.querySelectorAll(".m4w-cc-cat-checkbox");
		checkboxes.forEach(function (cb) {
			data[cb.getAttribute("data-slug")] = cb.checked ? "yes" : "no";
		});
		saveConsent(data);
		activateScripts(data);
		updateGcm(data);
		applyWpConsent(data);
		fireConsentEvent(data);
		hideModal();
		hideBanner();
	}

	function showModal() {
		var existing = getExistingConsent() || {};
		var checkboxes = document.querySelectorAll(".m4w-cc-cat-checkbox");
		checkboxes.forEach(function (cb) {
			var slug = cb.getAttribute("data-slug");
			if (!cb.disabled) cb.checked = existing[slug] === "yes";
		});
		modal.classList.remove("m4w-cc-hidden");
	}

	function hideModal() {
		modal.classList.add("m4w-cc-hidden");
	}

	function hideBanner() {
		banner.style.display = "none";
	}

	banner.addEventListener("click", function (e) {
		var existing = getExistingConsent() || {};
		if (e.target.classList.contains("m4w-cc-accept-all")) acceptAll(existing);
		else if (e.target.classList.contains("m4w-cc-reject-all")) rejectAll(existing);
		else if (e.target.classList.contains("m4w-cc-customize")) showModal();
	});

	modal.addEventListener("click", function (e) {
		var existing = getExistingConsent() || {};
		if (e.target.classList.contains("m4w-cc-modal-overlay") || e.target.classList.contains("m4w-cc-modal-close")) {
			hideModal();
		} else if (e.target.classList.contains("m4w-cc-accept-all")) {
			hideModal();
			acceptAll(existing);
		} else if (e.target.classList.contains("m4w-cc-save")) {
			saveCustom(existing);
		}
	});

	var existing = getExistingConsent();
	if (existing && existing.action === "yes") {
		activateScripts(existing);
		updateGcm(existing);
		applyWpConsent(existing);
		banner.style.display = "none";
	} else {
		banner.style.display = "block";
	}
})();
