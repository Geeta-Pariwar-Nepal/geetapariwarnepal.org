/* =========================================================
   Geeta Pariwar Nepal — Public Booking Calendar v2.3
   Hamro-Patro-style booking calendar.
   • Daily tab  : monthly calendar, ONE admin-assigned Adhyaya per
                  date. Click a date → popup (date, adhyaya, reciter,
                  status, book).
   • Weekly tab : monthly calendar (Saturdays). Click a date → popup
                  with all 18 chapters, each Booked/Available.
   • Booking popup posts to the existing gppb_submit_booking /
     gppb_edit_booking APIs and refreshes the calendar with no reload.
   No dashboard cards, no analytics, no 18-box daily grid.
   ========================================================= */
(function () {
	'use strict';

	function boot() {
	var root = document.getElementById('gppb-root');

	var CONFIG = window.GPPB_PUBLIC || {};
	var ajax = CONFIG.ajaxUrl || '';
	var nonce = CONFIG.nonce || '';
	var i18n = CONFIG.i18n || {};

	function t(key) { return i18n[key] || ''; }

	var state = {};
	if (root) {
		try { state = JSON.parse(root.getAttribute('data-state')) || {}; } catch (e) { state = {}; }
	}

	var isGuest = !!(state.guest);
	var prn = state.prn || '';
	var prnName = state.prnName || '';
	var prnPhone = '';
	var prnEmail = '';

	var currentType = 'daily';
	var currentDate = todayIso();
	var dailyCal = { year: 0, month: 0, data: null };
	var weeklyCal = { year: 0, month: 0, data: null };
	var editBookingId = 0;
	var editBookingDate = '';
	var booking = { type: 'daily', date: '', number: 0 };
	var registration = {};

	/* ---------- helpers ---------- */

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function nepaliNum(n) {
		var map = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];
		return String(n).split('').map(function (c) { return map[+c] || c; }).join('');
	}

	function alert(msg, type) {
		var area = document.getElementById('gppb-alert');
		if (!area) return;
		area.className = 'gpn-alert-area gpn-alert-' + (type || 'info');
		area.innerHTML = msg;
		if (type === 'success') { setTimeout(function () { area.className = 'gpn-alert-area'; area.innerHTML = ''; }, 6000); }
	}

	var REQUEST_TIMEOUT = 25000;
	var pendingRequests = {};

	function request(action, data, done) {
		var settled = false;
		function finish(err, res) {
			if (settled) return;
			settled = true;
			if (done) done(err, res);
		}

		var fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', nonce);
		for (var k in data) { if (data.hasOwnProperty(k)) fd.append(k, data[k]); }

		var ctrl = null;
		if (typeof AbortController !== 'undefined') ctrl = new AbortController();
		var timer = setTimeout(function () {
			if (ctrl) ctrl.abort();
			finish(t('error') + ' (समय सकियो — सर्भरले प्रतिक्रिया दिएन)');
		}, REQUEST_TIMEOUT);

		var opts = { method: 'POST', credentials: 'same-origin', body: fd };
		if (ctrl) opts.signal = ctrl.signal;

		fetch(ajax, opts)
			.then(function (r) {
				return r.text().then(function (text) {
					return { status: r.status, text: text };
				});
			})
			.then(function (r) {
				clearTimeout(timer);
				var res = null;
				try { res = JSON.parse(r.text); } catch (e) { res = null; }
				if (res && res.success) { finish(null, res.data); return; }
				if (res && res.data) {
					finish((res.data && res.data.message) || res.data || t('error'));
					return;
				}
				if (r.status === 403) {
					finish('बुकिङ फारमको सत्र समाप्त भएको हुनसक्छ । पृष्ठ ताजा गरेर फेरि प्रयास गर्नुहोस् । (HTTP 403 — nonce)');
					return;
				}
				finish('सर्भरबाट प्रतिक्रिया आएन (HTTP ' + r.status + ') । ' + t('error'));
			})
			.catch(function (err) {
				clearTimeout(timer);
				if (settled) return;
				if (err && err.name === 'AbortError') {
					finish(t('error') + ' (समय सकियो — सर्भरले प्रतिक्रिया दिएन)');
					return;
				}
				finish('सर्भरमा सम्पर्क हुन सकेन (' + ajax + ') । इन्टरनेट जाँच गरी फेरि प्रयास गर्नुहोस् ।');
			});
	}

	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (html !== undefined) n.innerHTML = html;
		return n;
	}

	function todayIso() {
		var d = new Date();
		var m = ('0' + (d.getMonth() + 1)).slice(-2);
		var day = ('0' + d.getDate()).slice(-2);
		return d.getFullYear() + '-' + m + '-' + day;
	}

	function formatIso(iso) {
		var d = new Date(iso + 'T00:00:00');
		return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
	}

	function dayLookup(iso, type) {
		var data = type === 'weekly' ? weeklyCal.data : dailyCal.data;
		if (!data || !data.days) return null;
		for (var i = 0; i < data.days.length; i++) {
			if (data.days[i].iso === iso) return data.days[i];
		}
		return null;
	}

	function stepCal(cal, dir) {
		if (!cal.year || !cal.month) {
			var now = new Date();
			cal.year = now.getFullYear();
			cal.month = now.getMonth() + 1;
		}
		cal.month += dir;
		if (cal.month < 1) { cal.month = 12; cal.year--; }
		if (cal.month > 12) { cal.month = 1; cal.year++; }
	}

	/* ---------- tabs ---------- */

	function switchTab(name) {
		if (name === 'daily' || name === 'weekly') currentType = name;
		document.querySelectorAll('[data-gpn-tab]').forEach(function (b) {
			b.classList.toggle('gpn-tab-active', b.getAttribute('data-gpn-tab') === name);
		});
		document.querySelectorAll('[data-gpn-pane]').forEach(function (p) {
			p.classList.toggle('d-none', p.getAttribute('data-gpn-pane') !== name);
		});
		closeModal();
		if (name === 'daily' || name === 'weekly') loadBookingPane();
		if (name === 'my') loadMyBookings();
		if (name === 'history') loadHistory();
	}

	/* ---------- modal ---------- */

	var modal = el('div', 'gpn-modal');
	modal.hidden = true;
	modal.innerHTML =
		'<div class="gpn-modal-backdrop" data-gpn-close></div>' +
		'<div class="gpn-modal-box" role="dialog">' +
		'  <button type="button" class="gpn-modal-x" data-gpn-close aria-label="बन्द">&times;</button>' +
		'  <div class="gpn-modal-body" id="gppb-modal-body"></div>' +
		'</div>';
	if (document.body) document.body.appendChild(modal);

	function showModal(html) {
		document.getElementById('gppb-modal-body').innerHTML = html;
		modal.hidden = false;
	}

	function closeModal() {
		modal.hidden = true;
		var body = document.getElementById('gppb-modal-body');
		if (body) body.innerHTML = '';
	}

	/* ---------- calendar shells ---------- */

	function calendarShell(type) {
		var prevId = type === 'daily' ? 'gppb-dcal-prev' : 'gppb-wcal-prev';
		var nextId = type === 'daily' ? 'gppb-dcal-next' : 'gppb-wcal-next';
		var monthId = type === 'daily' ? 'gppb-dcal-month' : 'gppb-wcal-month';
		var gridId = type === 'daily' ? 'gppb-dcal-grid' : 'gppb-wcal-grid';

		var editBanner = '';
		if (editBookingId) {
			editBanner =
				'<div class="gpn-edit-banner">' +
				'  <span><strong>बुकिङ सच्याउँदैछ</strong> — नयाँ मिति/अध्याय चयन गरी सुरक्षित गर्नुहोस् ।</span>' +
				'  <button type="button" class="btn btn-sm btn-light" id="gppb-cancel-edit">रद्द गर्नुहोस्</button>' +
				'</div>';
		}

		return editBanner +
			'<div class="gpn-cal-card">' +
			'  <div class="gpn-cal-head">' +
			'    <button type="button" class="gpn-cal-nav" id="' + prevId + '" title="' + t('prevMonth') + '">&#8249;</button>' +
			'    <div class="gpn-cal-title"><strong id="' + monthId + '"></strong></div>' +
			'    <button type="button" class="gpn-cal-nav" id="' + nextId + '" title="' + t('nextMonth') + '">&#8250;</button>' +
			'  </div>' +
			'  <div class="gpn-cal-name">' + (type === 'daily' ? t('dailyParayan') : t('weeklyParayan')) + '</div>' +
			'  <div class="gpn-legend">' + legendHtml(type) + '</div>' +
			'  <div class="gpn-cal-grid" id="' + gridId + '"><div class="gpn-loading">' + t('loading') + '</div></div>' +
			'</div>';
	}

	function legendHtml(type) {
		var items;
		if (type === 'daily') {
			items = [
				['gpn-dot-full', t('fullyBooked')],
				['gpn-dot-open', t('noBookings')],
				['gpn-dot-none', 'अध्याय तोकिएको छैन'],
				['gpn-dot-past', t('past')],
				['gpn-dot-today', t('today')]
			];
		} else {
			items = [
				['gpn-dot-full', t('fullyBooked')],
				['gpn-dot-partial', t('partial')],
				['gpn-dot-open', t('noBookings')],
				['gpn-dot-past', t('past')],
				['gpn-dot-today', t('today')]
			];
		}
		return items.map(function (it) {
			return '<span class="gpn-legend-item"><span class="gpn-dot ' + it[0] + '"></span>' + esc(it[1]) + '</span>';
		}).join('');
	}

	function loadBookingPane() {
		if (currentType !== 'daily' && currentType !== 'weekly') return;
		var pane = document.querySelector('[data-gpn-pane="' + currentType + '"]');
		if (!pane) return;
		pane.innerHTML = calendarShell(currentType);

		if (editBookingId) {
			var cancelEdit = document.getElementById('gppb-cancel-edit');
			if (cancelEdit) cancelEdit.addEventListener('click', function () {
				editBookingId = 0;
				editBookingDate = '';
				booking = { type: currentType, date: '', number: 0 };
				loadBookingPane();
			});
		}

		var prev = document.getElementById(currentType === 'daily' ? 'gppb-dcal-prev' : 'gppb-wcal-prev');
		var next = document.getElementById(currentType === 'daily' ? 'gppb-dcal-next' : 'gppb-wcal-next');
		if (prev) prev.addEventListener('click', function () {
			if (currentType === 'daily') { stepCal(dailyCal, -1); loadDailyCalendar(); }
			else { stepCal(weeklyCal, -1); loadWeeklyCalendar(); }
		});
		if (next) next.addEventListener('click', function () {
			if (currentType === 'daily') { stepCal(dailyCal, 1); loadDailyCalendar(); }
			else { stepCal(weeklyCal, 1); loadWeeklyCalendar(); }
		});

		if (currentType === 'daily') loadDailyCalendar();
		else loadWeeklyCalendar();
	}

	/* ---------- daily calendar ---------- */

	function loadDailyCalendar() {
		var c = dailyCal;
		var y = c.year, m = c.month;
		if (!y || !m) {
			var now = new Date();
			y = c.year = now.getFullYear();
			m = c.month = now.getMonth() + 1;
		}
		if (c.data && c.data.year === y && c.data.month === m) {
			renderDailyCalendar(c.data);
			return;
		}
		request('gppb_get_calendar', { slot_type: 'daily', year: y, month: m }, function (err, data) {
			if (err || !data) return;
			c.data = data;
			c.year = data.year;
			c.month = data.month;
			renderDailyCalendar(data);
		});
	}

	function renderDailyCalendar(data) {
		var grid = document.getElementById('gppb-dcal-grid');
		if (!grid) return;
		var title = document.getElementById('gppb-dcal-month');
		if (title) title.textContent = data.monthLabel;

		grid.innerHTML = '';
		data.weekdays.forEach(function (w) { grid.appendChild(el('div', 'gpn-cal-dow', w)); });
		for (var i = 0; i < data.leading; i++) grid.appendChild(el('div', 'gpn-cal-day gpn-cal-blank'));

		data.days.forEach(function (d) {
			var clickable = d.bookable && !d.past;
			var cls = 'gpn-cal-day';
			if (clickable) {
				cls += ' gpn-cal-' + (d.status || 'none');
				if (d.today) cls += ' gpn-cal-today';
			} else {
				cls += d.past ? ' gpn-cal-past' : ' gpn-cal-off';
			}

			var cell = el(clickable ? 'button' : 'div', cls);
			if (clickable) {
				cell.type = 'button';
				cell.dataset.iso = d.iso;
				cell.dataset.type = 'daily';
			}
			cell.innerHTML =
				'<span class="gpn-cal-num">' + d.day + '</span>' +
				(d.assigned ? '<span class="gpn-cal-ch">' + nepaliNum(d.assigned) + '</span>' : '');
			grid.appendChild(cell);
		});
	}

	/* ---------- weekly calendar ---------- */

	function loadWeeklyCalendar() {
		var c = weeklyCal;
		var y = c.year, m = c.month;
		if (!y || !m) {
			var now = new Date();
			y = c.year = now.getFullYear();
			m = c.month = now.getMonth() + 1;
		}
		if (c.data && c.data.year === y && c.data.month === m) {
			renderWeeklyCalendar(c.data);
			return;
		}
		request('gppb_get_calendar', { slot_type: 'weekly', year: y, month: m }, function (err, data) {
			if (err || !data) return;
			c.data = data;
			c.year = data.year;
			c.month = data.month;
			renderWeeklyCalendar(data);
		});
	}

	function renderWeeklyCalendar(data) {
		var grid = document.getElementById('gppb-wcal-grid');
		if (!grid) return;
		var title = document.getElementById('gppb-wcal-month');
		if (title) title.textContent = data.monthLabel;

		grid.innerHTML = '';
		data.weekdays.forEach(function (w) { grid.appendChild(el('div', 'gpn-cal-dow', w)); });
		for (var i = 0; i < data.leading; i++) grid.appendChild(el('div', 'gpn-cal-day gpn-cal-blank'));

		data.days.forEach(function (d) {
			var isSession = d.bookable;
			var cls = 'gpn-cal-day';
			if (isSession) {
				cls += ' gpn-cal-' + (d.status || 'open');
				if (d.today) cls += ' gpn-cal-today';
			} else {
				cls += d.past ? ' gpn-cal-past' : ' gpn-cal-off';
			}

			var cell = el(isSession ? 'button' : 'div', cls);
			if (isSession) {
				cell.type = 'button';
				cell.dataset.iso = d.iso;
				cell.dataset.type = 'weekly';
			}
			cell.innerHTML =
				'<span class="gpn-cal-num">' + d.day + '</span>' +
				(isSession && d.occupied ? '<span class="gpn-cal-ch">' + d.occupied + '/' + d.total + '</span>' : '');
			grid.appendChild(cell);
		});
	}

	/* ---------- popups ---------- */

	function openDailyPopup(iso) {
		var day = dayLookup(iso, 'daily');
		showModal('<div class="gpn-popup-loading">' + t('loading') + '</div>');
		var pd = { slot_type: 'daily', date: iso };
		if (isGuest && prn) pd.prn = prn;
		request('gppb_get_availability', pd, function (err, data) {
			if (err || !data) { showModal('<div class="gpn-empty">' + t('error') + '</div>'); return; }
			renderDailyPopup(data, iso, day);
		});
	}

	function renderDailyPopup(data, iso, day) {
		var assigned = day && day.assigned ? day.assigned : 0;
		var assignedTitle = assigned ? 'अध्याय ' + nepaliNum(assigned) : 'यस दिनको अध्याय तोकिएको छैन';
		var dateLine = formatIso(iso) + (day && day.weekdayLabel ? ' · ' + day.weekdayLabel : '');

		var chapter = null;
		if (assigned) {
			(data.chapters || []).forEach(function (c) { if (c.number === assigned) chapter = c; });
		}

		var html =
			'<div class="gpn-pop-detail">' +
			'  <div class="gpn-pop-row"><span class="gpn-pop-label">मिति</span><strong>' + esc(dateLine) + '</strong></div>' +
			'  <div class="gpn-pop-row"><span class="gpn-pop-label">आजको अध्याय</span><strong>' + assignedTitle + '</strong></div>';

		var canBook = false;
		if (!assigned || !chapter) {
			html += '<div class="gpn-pop-note">यस दिनको लागि अध्याय तोकिएको छैन । कृपया पछि हेर्नुहोस् ।</div>';
		} else if (chapter.occupants && chapter.occupants.length) {
			var o = chapter.occupants[0];
			html +=
				'  <div class="gpn-pop-row"><span class="gpn-pop-label">स्थिति</span><span class="gpn-ch-state gpn-ch-booked">बुक भयो</span></div>' +
				'  <div class="gpn-pop-row"><span class="gpn-pop-label">बुक गरेका</span><span><strong>' + esc(o.name) + '</strong> <span class="gpn-pop-muted">(' + esc(o.prn) + ')</span></span></div>';
		} else {
			html += '  <div class="gpn-pop-row"><span class="gpn-pop-label">स्थिति</span><span class="gpn-ch-state gpn-ch-avail">खाली</span></div>';
			canBook = true;
		}

		var reason = '';
		if (canBook) {
			if (data.guestMode) {
				if (data.guestDupe) reason = t('prnRegistered');
			} else if (data.restricted && !chapter.overrideActive) reason = data.restrictionMsg || 'तपाईंले हालै पारायण गर्नुभएको छ। अर्को पारायणका लागि १ महिना पूरा भएपछि मात्र बुक गर्न सक्नुहुन्छ।';
			else if (data.approvalStatus !== 'approved') reason = data.approvalStatus === 'pending' ? 'बुकिङ गर्न गुरुज्यूको अनुमति पर्खनुहोस् ।' : 'बुकिङ गर्न अनुमति अस्वीकृत छ ।';
			else if (data.accountStatus === 'blocked') reason = 'तपाईंको खाता स्थगित गरिएको छ ।';
			else if (data.hasActiveBooking && !editBookingId) reason = 'तपाईंसँग पहिले नै सक्रिय बुकिङ छ ।';
		}

		if (canBook && !reason) {
			html +=
				'  <button type="button" class="btn btn-warning w-100 fw-semibold gpn-book-btn" data-type="daily" data-date="' + iso + '" data-number="' + assigned + '">बुक गर्नुहोस्</button>';
		} else if (reason) {
			html += '<div class="gpn-pop-note">' + esc(reason) + '</div>';
		}

		html += '</div>';
		showModal(html);
	}

	function openWeeklyPopup(iso) {
		var day = dayLookup(iso, 'weekly');
		showModal('<div class="gpn-popup-loading">' + t('loading') + '</div>');
		var pd = { slot_type: 'weekly', date: iso };
		if (isGuest && prn) pd.prn = prn;
		request('gppb_get_availability', pd, function (err, data) {
			if (err || !data) { showModal('<div class="gpn-empty">' + t('error') + '</div>'); return; }
			renderWeeklyPopup(data, iso, day);
		});
	}

	function renderWeeklyPopup(data, iso, day) {
		var dateLine = formatIso(iso) + (day && day.weekdayLabel ? ' · ' + day.weekdayLabel : '');
		var html =
			'<div class="gpn-pop-header"><strong>साप्ताहिक पारायण</strong><span class="gpn-pop-muted">' + esc(dateLine) + '</span></div>' +
			'<div class="gpn-ch-list">';

		(data.chapters || []).forEach(function (ch) {
			var booked = !!(ch.occupants && ch.occupants.length);
			var canBook = !booked && (data.guestMode ? true : (
				data.approvalStatus === 'approved' &&
				data.accountStatus !== 'blocked' &&
				!(data.hasActiveBooking && !editBookingId) &&
				!(data.restricted && !ch.overrideActive)
			));

			html +=
				'<div class="gpn-ch-row">' +
				'  <span class="gpn-ch-num">' + nepaliNum(ch.number) + '</span>' +
				'  <span class="gpn-ch-name">' + esc(ch.title) + '</span>' +
				'  <span class="gpn-ch-state ' + (booked ? 'gpn-ch-booked' : 'gpn-ch-avail') + '">' + (booked ? 'बुक भयो' : 'उपलब्ध') + '</span>' +
				(canBook ? '<button type="button" class="btn btn-sm btn-warning gpn-book-btn" data-type="weekly" data-date="' + iso + '" data-number="' + ch.number + '">बुक गर्नुहोस्</button>' : '') +
				'</div>';
		});

		html += '</div>';

		if (data.guestMode) {
			if (data.guestDupe) {
				html += '<div class="gpn-pop-note">' + esc(t('prnRegistered')) + '</div>';
			}
		} else if (data.restricted && !data.overrideActive) {
			html += '<div class="gpn-pop-note">' + esc(data.restrictionMsg || 'तपाईंले हालै पारायण गर्नुभएको छ। अर्को पारायणका लागि १ महिना पूरा भएपछि मात्र बुक गर्न सक्नुहुन्छ।') + '</div>';
		} else if (data.approvalStatus !== 'approved' || data.accountStatus === 'blocked') {
			var r = data.approvalStatus !== 'approved' ? 'बुकिङ गर्न गुरुज्यूको अनुमति आवश्यक छ ।' : 'तपाईंको खाता स्थगित गरिएको छ ।';
			html += '<div class="gpn-pop-note">' + esc(r) + '</div>';
		}

		showModal(html);
	}

	/* ---------- booking form ---------- */

	function openBookingForm(type, date, number) {
		booking = { type: type, date: date, number: number };
		var day = dayLookup(date, type);
		var dateLine = formatIso(date) + (day && day.weekdayLabel ? ' · ' + day.weekdayLabel : '');

		/* Guests first confirm their Sadhak details on the Google-Form-style
		   registration form, then reach the pledge/confirmation step. */
		if (isGuest) {
			showModal(registrationFormHtml(type, date, number, dateLine));
			return;
		}

		showModal(confirmationFormHtml(type, date, number, dateLine));
	}

	/* Google-Form-style registration / details form (restored from the
	   previous Parayan registration flow). PRN is already known from the
	   verified PRN and is shown read-only. */
	function registrationFormHtml(type, date, number, dateLine) {
		var levels = [
			['', t('regSelect')],
			['1', t('regLevel1')],
			['2', t('regLevel2')],
			['3', t('regLevel3')],
			['4', t('regLevel4')]
		];
		var parts = [
			['', t('regSelect')],
			['weekly', t('regWeekly')],
			['daily', t('regDaily')],
			['never', t('regNever')]
		];
		function levelOpts(sel) {
			return levels.map(function (l) {
				return '<option value="' + l[0] + '"' + (l[0] === sel ? ' selected' : '') + '>' + esc(l[1]) + '</option>';
			}).join('');
		}
		function partOpts(sel) {
			return parts.map(function (l) {
				return '<option value="' + l[0] + '"' + (l[0] === sel ? ' selected' : '') + '>' + esc(l[1]) + '</option>';
			}).join('');
		}

		var preName = registration.full_name || prnName || '';
		var preMobile = registration.mobile || prnPhone || '';
		var preEmail = registration.email || prnEmail || '';

		return '' +
			'<div class="gpn-pop-header"><strong>' + t('regTitle') + '</strong><span class="gpn-pop-muted">' + esc(dateLine) + '</span></div>' +
			'<p class="gpn-bk-decl">' + esc(t('regIntro')) + '</p>' +
			'<form class="gpn-reg-form" id="gppb-reg-form" novalidate>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-prn">' + t('prn') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="text" class="form-control" id="gppb-reg-prn" value="' + esc(prn) + '" readonly>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-name">' + t('regName') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="text" class="form-control" id="gppb-reg-name" value="' + esc(preName) + '" required>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-mobile">' + t('regMobile') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="tel" class="form-control" id="gppb-reg-mobile" value="' + esc(preMobile) + '" required>' +
			'    <div class="form-text">' + esc(t('regMobileHelp')) + '</div>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-district">' + t('regDistrict') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="text" class="form-control" id="gppb-reg-district" value="' + esc(registration.district || '') + '" required>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-place">' + t('regPlace') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="text" class="form-control" id="gppb-reg-place" value="' + esc(registration.place || '') + '" required>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-country">' + t('regCountry') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="text" class="form-control" id="gppb-reg-country" value="' + esc(registration.country || 'नेपाल') + '" required>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-country_code">' + t('regCountryCode') + ' <span class="gpn-reg-req">*</span></label>' +
			'    <input type="text" class="form-control" id="gppb-reg-country_code" value="' + esc(registration.country_code || '+977') + '" required>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-email">' + t('regEmail') + '</label>' +
			'    <input type="email" class="form-control" id="gppb-reg-email" value="' + esc(preEmail) + '">' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-completed_level">' + t('regCompleted') + '</label>' +
			'    <select class="form-select" id="gppb-reg-completed_level">' + levelOpts(registration.completed_level || '') + '</select>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-current_level">' + t('regCurrent') + '</label>' +
			'    <select class="form-select" id="gppb-reg-current_level">' + levelOpts(registration.current_level || '') + '</select>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-trainer_name">' + t('regTrainer') + '</label>' +
			'    <input type="text" class="form-control" id="gppb-reg-trainer_name" value="' + esc(registration.trainer_name || '') + '">' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-age">' + t('regAge') + '</label>' +
			'    <input type="number" class="form-control" id="gppb-reg-age" min="1" max="120" value="' + esc(registration.age || '') + '">' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-volunteer_services">' + t('regVolServices') + '</label>' +
			'    <input type="text" class="form-control" id="gppb-reg-volunteer_services" value="' + esc(registration.volunteer_services || '') + '">' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-trainer_level">' + t('regTrainerLvl') + '</label>' +
			'    <select class="form-select" id="gppb-reg-trainer_level">' + levelOpts(registration.trainer_level || '') + '</select>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-previous_participation">' + t('regPrevPart') + '</label>' +
			'    <select class="form-select" id="gppb-reg-previous_participation">' + partOpts(registration.previous_participation || '') + '</select>' +
			'  </div>' +
			'  <div class="gpn-reg-field">' +
			'    <label class="gpn-reg-label" for="gppb-reg-previous_date">' + t('regPrevDate') + '</label>' +
			'    <input type="date" class="form-control" id="gppb-reg-previous_date" value="' + esc(registration.previous_date || '') + '">' +
			'  </div>' +
			'</form>' +
			'<button type="button" class="btn btn-warning w-100 fw-semibold" id="gppb-reg-continue">' + t('regContinue') + '</button>';
	}

	function confirmationFormHtml(type, date, number, dateLine) {
		return '' +
			'<div class="gpn-pop-header"><strong>बुकिङ पुष्टि</strong></div>' +
			'<div class="gpn-pop-detail">' +
			'  <div class="gpn-pop-row"><span class="gpn-pop-label">प्रकार</span><span>' + (type === 'daily' ? 'दैनिक पारायण' : 'साप्ताहिक पारायण') + '</span></div>' +
			'  <div class="gpn-pop-row"><span class="gpn-pop-label">मिति</span><span>' + esc(dateLine) + '</span></div>' +
			'  <div class="gpn-pop-row"><span class="gpn-pop-label">अध्याय</span><span>अध्याय ' + nepaliNum(number) + '</span></div>' +
			'</div>' +
			'<p class="gpn-bk-decl">म उपर्युक्त मिति र अध्यायमा श्रीमद्भगवद्गीता पारायण गर्ने प्रतिबद्धता व्यक्त गर्दछु ।</p>' +
			'<button type="button" class="btn btn-warning w-100 fw-semibold" id="gppb-submit">' +
			(editBookingId ? 'सच्याइएको बुकिङ सुरक्षित गर्नुहोस्' : 'बुकिङ सुरक्षित गर्नुहोस्') +
			'</button>';
	}

	function collectRegistration() {
		['name', 'mobile', 'district', 'place', 'country', 'country_code', 'email', 'completed_level', 'current_level', 'trainer_name', 'age', 'volunteer_services', 'trainer_level', 'previous_participation', 'previous_date'].forEach(function (f) {
			var el = document.getElementById('gppb-reg-' + f);
			if (el) registration[f === 'name' ? 'full_name' : f] = el.value.trim();
		});
	}

	function submitRegistration(btn) {
		var required = ['name', 'mobile', 'district', 'place', 'country', 'country_code'];
		for (var i = 0; i < required.length; i++) {
			var el = document.getElementById('gppb-reg-' + required[i]);
			if (el && !el.value.trim()) {
				el.classList.add('is-invalid');
				el.focus();
				alert(t('required'), 'danger');
				return;
			}
		}
		collectRegistration();
		closeModal();
		var day = dayLookup(booking.date, booking.type);
		var dateLine = formatIso(booking.date) + (day && day.weekdayLabel ? ' · ' + day.weekdayLabel : '');
		showModal(confirmationFormHtml(booking.type, booking.date, booking.number, dateLine));
	}

	var submitting = false;

	function submitBooking(submit) {
		if (!booking.number) { alert(t('required'), 'danger'); return; }
		if (submitting) return;
		submitting = true;
		submit.disabled = true;
		submit.textContent = t('processing');

		var finish = function (err, d2) {
			submitting = false;
			submit.disabled = false;
			submit.textContent = editBookingId ? 'सच्याइएको बुकिङ सुरक्षित गर्नुहोस्' : 'बुकिङ सुरक्षित गर्नुहोस्';
			if (err) { alert(err, 'danger'); return; }
			alert(d2.message || 'सफल भयो ।', 'success');
			var wasType = booking.type;
			closeModal();
			editBookingId = 0;
			editBookingDate = '';
			booking = { type: 'daily', date: '', number: 0 };
			if (currentType === 'daily' || currentType === 'weekly') reloadCalendars();
			if (currentType !== wasType) switchTab(wasType);
		};

		if (editBookingId) {
			request('gppb_edit_booking', {
				booking_id: editBookingId,
				date: booking.date,
				adhyaya_number: booking.number
			}, finish);
		} else {
			var pd = {
				slot_type: booking.type,
				date: booking.date,
				adhyaya_number: booking.number
			};
			if (isGuest && prn) {
				pd.prn = prn;
				/* Send the Google-Form-style registration details along. */
				['full_name', 'mobile', 'district', 'place', 'country', 'country_code', 'email', 'completed_level', 'current_level', 'trainer_name', 'age', 'volunteer_services', 'trainer_level', 'previous_participation', 'previous_date'].forEach(function (f) {
					if (registration[f] !== undefined && registration[f] !== '') pd[f] = registration[f];
				});
			}
			request('gppb_submit_booking', pd, finish);
		}
	}

	function reloadCalendars() {
		var c = currentType === 'daily' ? dailyCal : weeklyCal;
		c.data = null;
		if (currentType === 'daily') loadDailyCalendar();
		else loadWeeklyCalendar();
	}

	/* ---------- my bookings ---------- */

	function loadMyBookings() {
		var wrap = document.getElementById('gppb-my-bookings');
		if (!wrap) return;
		wrap.innerHTML = '<div class="gpn-loading">' + t('loading') + '</div>';

		request('gppb_my_bookings', {}, function (err, data) {
			if (err || !data) { wrap.innerHTML = '<div class="gpn-empty">' + t('error') + '</div>'; return; }
			renderMyBookings(wrap, data);
		});
	}

	function renderMyBookings(wrap, data) {
		wrap.innerHTML = '';

		if (data.accountStatus === 'blocked') {
			var blocked = el('div', 'gpn-alert-blocked');
			blocked.innerHTML =
				'<h4>खाता स्थगित</h4>' +
				'<p>तपाईंको खाता स्थगित गरिएको छ । कृपया अनब्लक अनुरोध पेश गर्नुहोस् ।</p>' +
				(data.unblockReason ? '<p class="small text-muted">पेश गरिएको अनुरोध: ' + esc(data.unblockReason) + '</p>' : '');
			if (!data.unblockReason) {
				var form = el('form', 'gpn-unblock-form');
				form.innerHTML =
					'<div class="mb-2"><textarea class="form-control" id="gppb-unblock-reason" rows="3" placeholder="कारण लेख्नुहोस्"></textarea></div>' +
					'<button type="submit" class="btn btn-warning">अनब्लक अनुरोध पेश गर्नुहोस्</button>';
				form.addEventListener('submit', function (ev) {
					ev.preventDefault();
					var reason = document.getElementById('gppb-unblock-reason').value.trim();
					if (!reason) { alert(t('required'), 'danger'); return; }
					request('gppb_unblock_request', { reason: reason }, function (e2, d2) {
						if (e2) { alert(e2, 'danger'); return; }
						alert(d2.message || 'अनुरोध पेश भयो ।', 'success');
						loadMyBookings();
					});
				});
				blocked.appendChild(form);
			}
			wrap.appendChild(blocked);
		}

		if (data.approvalStatus === 'pending') {
			wrap.appendChild(el('div', 'gpn-alert-info', 'अनुमति आउन बाँकी: बुकिङ गर्न गुरुज्यूको अनुमति आवश्यक छ ।'));
		} else if (data.approvalStatus === 'rejected') {
			wrap.appendChild(el('div', 'gpn-alert-danger', 'तपाईंको अनुमति अस्वीकृत भएको छ । कृपया सम्पर्क गर्नुहोस् ।'));
		}

		if (!data.bookings.length) {
			wrap.appendChild(el('div', 'gpn-empty', 'हालसम्म कुनै बुकिङ भएको छैन ।'));
		}

		data.bookings.forEach(function (b) {
			var card = el('div', 'gpn-result-card' + (b.active ? ' gpn-active-booking' : ''));
			var actions = '';
			if (b.active) {
				actions =
					'<div class="gpn-result-actions">' +
					'  <button class="btn btn-sm btn-outline-warning gpn-edit-bk" data-id="' + b.id + '" data-slot="' + b.slot_type + '" data-date="' + b.date + '">सच्याउनुहोस्</button>' +
					'  <button class="btn btn-sm btn-outline-danger gpn-cancel-bk" data-id="' + b.id + '">रद्द गर्नुहोस्</button>' +
					'</div>';
			}
			var links = '';
			if (b.zoom_link) links += '<div class="gpn-meeting-box">Zoom: <a href="' + esc(b.zoom_link) + '" target="_blank" rel="noopener">' + esc(b.zoom_link) + '</a></div>';
			if (b.youtube_link) links += '<div class="gpn-meeting-box">YouTube: <a href="' + esc(b.youtube_link) + '" target="_blank" rel="noopener">' + esc(b.youtube_link) + '</a></div>';

			card.innerHTML =
				'<div class="gpn-result-header">' +
				'  <div><strong>' + esc(b.adhyaya) + '</strong> <span class="text-muted">(अध्याय ' + b.adhyaya_number + ')</span></div>' +
				'  <span class="gpn-status gpn-status-' + b.status + '">' + esc(b.status_label) + '</span>' +
				'</div>' +
				'<div class="gpn-result-meta">' +
				'  <span><strong>PRN:</strong> ' + esc(b.prn) + '</span>' +
				'  <span><strong>मिति:</strong> ' + esc(b.formatted) + ' (' + esc(b.nepali) + ')</span>' +
				'  <span><strong>प्रकार:</strong> ' + esc(b.type_label) + '</span>' +
				'</div>' +
				links + actions;

			wrap.appendChild(card);
		});
	}

	/* ---------- history / roster ---------- */

	function loadHistory() {
		var wrap = document.getElementById('gppb-history');
		if (!wrap) return;
		wrap.innerHTML =
			'<div class="gpn-history-toolbar">' +
			'  <label class="gpn-field">' +
			'    <span class="gpn-field-label">मिति</span>' +
			'    <input type="date" class="form-control" id="gppb-history-date" max="' + todayIso() + '">' +
			'  </label>' +
			'  <button class="btn btn-warning" id="gppb-history-go">हेर्नुहोस्</button>' +
			'</div>' +
			'<div id="gppb-history-result" class="gpn-history-result"></div>';

		var go = document.getElementById('gppb-history-go');
		var input = document.getElementById('gppb-history-date');
		go.addEventListener('click', function () { loadRoster(input.value); });
		input.addEventListener('change', function () { loadRoster(input.value); });
	}

	function loadRoster(date) {
		var out = document.getElementById('gppb-history-result');
		if (!out) return;
		if (!date) { out.innerHTML = '<div class="gpn-empty">मिति छान्नुहोस् ।</div>'; return; }
		out.innerHTML = '<div class="gpn-loading">' + t('loading') + '</div>';

		request('gppb_get_roster', { slot_type: 'daily', date: date }, function (err, data) {
			if (err || !data) { out.innerHTML = '<div class="gpn-empty">' + t('error') + '</div>'; return; }
			if (!data.roster.length) { out.innerHTML = '<div class="gpn-empty">त्यो दिन कुनै पारायण भएको छैन ।</div>'; return; }

			var html = '<h4 class="gpn-history-title">' + esc(data.date) + ' को रोस्टर</h4><table class="table table-sm gpn-history-table"><thead><tr><th>अध्याय</th><th>साधक</th><th>PRN</th><th>स्थिति</th></tr></thead><tbody>';
			data.roster.forEach(function (r) {
				html += '<tr><td>' + esc(r.adhyaya) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.prn) + '</td><td><span class="gpn-status gpn-status-' + r.status + '">' + esc(r.status) + '</span></td></tr>';
			});
			html += '</tbody></table>';
			out.innerHTML = html;
		});
	}

	/* ---------- guest / PRN mode ---------- */

	function guestShell() {
		var settings = state.settings || {};
		var tabs = 'daily|weekly|history';
		return '' +
			'<div class="gpn-shell">' +
			'  <div class="gpn-dash-head">' +
			'    <div class="gpn-dash-brand">' +
			(settings.logoUrl ? '<img src="' + esc(settings.logoUrl) + '" alt="" class="gpn-dash-logo">' : '<span class="gpn-brand-om">ॐ</span>') +
			'      <div class="gpn-dash-brand-text">' +
			'        <span class="gpn-dash-title">' + esc(settings.landingTitle || t('dailyParayan')) + '</span>' +
			'        <span class="gpn-dash-sub">श्रीमद्भगवद्गीता पारायण</span>' +
			'      </div>' +
			'    </div>' +
			'    <div class="gpn-dash-user">' +
			'      <span class="gpn-dash-welcome">' + t('welcome') + ', ' + esc(prnName) + ' <span class="gpn-pop-muted">(' + esc(prn) + ')</span></span>' +
			'      <button type="button" class="gpn-dash-logout" id="gppb-prn-reset">' + t('prnChange') + '</button>' +
			'    </div>' +
			'  </div>' +
			'  <nav class="gpn-dash-tabs" role="tablist">' +
			'    <button class="gpn-tab gpn-tab-active" data-gpn-tab="daily" role="tab">' + t('dailyParayan') + '</button>' +
			'    <button class="gpn-tab" data-gpn-tab="weekly" role="tab">' + t('weeklyParayan') + '</button>' +
			'    <button class="gpn-tab" data-gpn-tab="history" role="tab">' + t('history') + '</button>' +
			'  </nav>' +
			'  <div class="gpn-dash-body">' +
			'    <div class="gpn-pane" data-gpn-pane="daily"></div>' +
			'    <div class="gpn-pane d-none" data-gpn-pane="weekly"></div>' +
			'    <div class="gpn-pane d-none" data-gpn-pane="history">' +
			'      <div id="gppb-history"></div>' +
			'    </div>' +
			'  </div>' +
			'  <div class="gpn-alert-area" id="gppb-alert" role="alert" aria-live="polite"></div>' +
			'</div>';
	}

	function verifyPrn() {
		var input = document.getElementById('gppb-prn-input');
		var errBox = document.getElementById('gppb-prn-error');
		if (!input) return;
		var value = input.value.trim();
		if (errBox) errBox.innerHTML = '';
		if (!value) { if (errBox) errBox.innerHTML = '<div class="gpn-empty">' + t('prnRequired') + '</div>'; return; }

		request('gppb_verify_prn', { prn: value }, function (err, data) {
			if (err) { if (errBox) errBox.innerHTML = '<div class="gpn-empty">' + esc(err) + '</div>'; return; }
			prn = data.prn;
			prnName = data.name || '';
			prnPhone = data.phone || '';
			prnEmail = data.email || '';
			state.prn = prn;
			state.prnName = prnName;
			var gate = document.getElementById('gppb-prn-gate');
			var root = document.getElementById('gppb-root');
			if (gate) gate.remove();
			if (root) {
				root.innerHTML = guestShell();
				root.className = 'gpn-pb-public';
				alert(t('prnVerified') + ' 🙏', 'success');
			}
			loadBookingPane();
		});
	}

	function resetPrn() {
		prn = '';
		prnName = '';
		delete state.prn;
		delete state.prnName;
		var root = document.getElementById('gppb-root');
		if (root) root.innerHTML = ''; /* full page refresh re-renders the gate */
	}

	/* ---------- delegated actions ---------- */

	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (f && f.id === 'gppb-reg-form') {
			e.preventDefault();
			var btn = document.getElementById('gppb-reg-continue');
			if (btn) submitRegistration(btn);
		}
	});

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || typeof t.closest !== 'function') return;

		var close = t.closest('[data-gpn-close]');
		if (close) { closeModal(); return; }

		var tab = t.closest('[data-gpn-tab]');
		if (tab) { switchTab(tab.getAttribute('data-gpn-tab')); return; }

		var dayCell = t.closest('.gpn-cal-day');
		if (dayCell && dayCell.dataset.iso) {
			if (dayCell.dataset.type === 'weekly') openWeeklyPopup(dayCell.dataset.iso);
			else openDailyPopup(dayCell.dataset.iso);
			return;
		}

		var book = t.closest('.gpn-book-btn');
		if (book) {
			openBookingForm(book.dataset.type, book.dataset.date, parseInt(book.dataset.number, 10));
			return;
		}

		var submit = t.closest('#gppb-submit');
		if (submit && !submit.disabled) {
			submitBooking(submit);
			return;
		}

		var regContinue = t.closest('#gppb-reg-continue');
		if (regContinue) { submitRegistration(regContinue); return; }

		var verify = t.closest('#gppb-prn-verify');
		if (verify) { verifyPrn(); return; }

		var reset = t.closest('#gppb-prn-reset');
		if (reset) { resetPrn(); window.location.reload(); return; }

		var cancel = t.closest('.gpn-cancel-bk');
		if (cancel) {
			if (!window.confirm(t('confirmCancel'))) return;
			cancel.disabled = true;
			request('gppb_cancel_booking', { booking_id: cancel.dataset.id }, function (e2, d2) {
				cancel.disabled = false;
				if (e2) { alert(e2, 'danger'); return; }
				alert(d2.message || 'बुकिङ रद्द भयो ।', 'success');
				loadMyBookings();
			});
			return;
		}

		var editbk = t.closest('.gpn-edit-bk');
		if (editbk) {
			editBookingId = parseInt(editbk.dataset.id, 10);
			editBookingDate = editbk.dataset.date;
			switchTab(editbk.dataset.slot);
			alert('नयाँ मिति/अध्याय चयन गरी सुरक्षित गर्नुहोस् ।', 'info');
			return;
		}

		var prnInput = t.closest('#gppb-prn-input');
		if (prnInput) {
			prnInput.addEventListener('keydown', function (ev) {
				if (ev.key === 'Enter') { ev.preventDefault(); verifyPrn(); }
			});
			return;
		}
	});

	/* ---------- init ---------- */
	if (!ajax) {
		if (root) {
			root.innerHTML =
				'<div class="gpn-empty">कन्फिगरेसन लोड हुन सकेन ।</div>' +
				'<div class="gpn-hint">GPPB_PUBLIC कन्फिगरेसन फेला परेन — प्लगइन नयाँ संस्करणमा अद्यावधिक छैन वा यो पेजमा shortcode बिना लोड भएको हुनसक्छ । पेज hard-refresh (Ctrl+F5) गरेर हेर्नुहोस् ।</div>';
		}
		return;
	}
	if (!root) return;
	if (isGuest) {
		if (document.getElementById('gppb-prn-gate')) return; /* gate waits for verification */
	}
	loadBookingPane();
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}
	ready(boot);
})();
