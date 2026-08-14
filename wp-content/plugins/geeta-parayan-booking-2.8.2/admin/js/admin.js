/* =========================================================
   Geeta Pariwar Nepal — Admin Booking JS
   ========================================================= */
(function () {
	'use strict';

	if (!window.GPPB_ADMIN) return;
	var C = window.GPPB_ADMIN;
	var ajax = C.ajaxUrl || '';
	var nonce = C.nonce || '';
	var i18n = C.i18n || {};

	function post(action, data, done) {
		var fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', nonce);
		for (var k in data) { if (data.hasOwnProperty(k)) fd.append(k, data[k]); }
		fetch(ajax, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) done(null, res.data);
				else done((res && res.data && res.data.message) || i18n.error);
			})
			.catch(function () { done(i18n.error); });
	}

	function flash(msg, ok) {
		var box = document.createElement('div');
		box.className = 'notice notice-' + (ok ? 'success' : 'error') + ' is-dismissible';
		box.style.position = 'fixed';
		box.style.top = '46px';
		box.style.right = '20px';
		box.style.zIndex = 9999;
		box.innerHTML = '<p>' + msg + '</p>';
		document.body.appendChild(box);
		setTimeout(function () { box.remove(); }, 4000);
	}

	function confirmAction(text) {
		return window.confirm(text || i18n.confirm);
	}

	/* Approvals */
	document.querySelectorAll('.gpn-approve').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_set_approval', { user_id: btn.dataset.id, status: 'approved' }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	document.querySelectorAll('.gpn-reject').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_set_approval', { user_id: btn.dataset.id, status: 'rejected' }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	/* Unblocks */
	document.querySelectorAll('.gpn-unblock').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_set_account', { user_id: btn.dataset.id, status: 'active' }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	document.querySelectorAll('.gpn-block').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_set_account', { user_id: btn.dataset.id, status: 'blocked' }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	/* Roster: mark completed */
	document.querySelectorAll('.gpn-complete').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_mark_completed', { booking_id: btn.dataset.id }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	/* Roster: delete booking (soft delete, slot re-opens) */
	document.querySelectorAll('.gpn-delete-bk').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.confirm('Are you sure you want to delete this booking?')) return;
			btn.disabled = true;
			post('gppb_admin_delete_booking', { booking_id: btn.dataset.id }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	/* Approvals: grant a booking-scoped early-booking override (modal) */
	document.querySelectorAll('.gpn-grant-override').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.getElementById('gppb-override-user').value = btn.dataset.id || '';
			document.getElementById('gppb-override-name').value = btn.dataset.name || '';
			document.getElementById('gppb-override-date').value = '';
		});
	});
	var overrideForm = document.getElementById('gppb-override-form');
	if (overrideForm) {
		overrideForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var data = {
				user_id: document.getElementById('gppb-override-user').value,
				slot_type: document.getElementById('gppb-override-type').value,
				date: document.getElementById('gppb-override-date').value,
				adhyaya_number: document.getElementById('gppb-override-chapter').value
			};
			if (!data.user_id || !data.date) { flash(i18n.error, false); return; }
			var submitBtn = overrideForm.querySelector('[type="submit"]');
			if (submitBtn) submitBtn.disabled = true;
			post('gppb_admin_grant_override', data, function (err) {
				if (submitBtn) submitBtn.disabled = false;
				if (err) { flash(err, false); return; }
				var modalEl = document.getElementById('gppbOverrideModal');
				if (window.bootstrap && modalEl) { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	}

	/* Approvals: revoke a booking-scoped early-booking override */
	document.querySelectorAll('.gpn-revoke-override').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_revoke_override', { override_id: btn.dataset.id }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	/* Session links */
	var linkForm = document.getElementById('gppb-link-form');
	if (linkForm) {
		linkForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var data = {
				slot_type: linkForm.querySelector('[name="slot_type"]').value,
				date: linkForm.querySelector('[name="date"]').value,
				zoom_link: linkForm.querySelector('[name="zoom_link"]').value,
				youtube_link: linkForm.querySelector('[name="youtube_link"]').value
			};
			if (!data.date) { flash(i18n.error, false); return; }
			post('gppb_admin_save_link', data, function (err) {
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				setTimeout(function () { location.reload(); }, 600);
			});
		});
	}

	/* PRN Master */
	var prnForm = document.getElementById('gppb-prn-form');
	if (prnForm) {
		prnForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(prnForm);
			var data = {};
			fd.forEach(function (v, k) { data[k] = v; });
			if (!data.prn || !data.name) { flash(i18n.error, false); return; }
			var submitBtn = prnForm.querySelector('[type="submit"]');
			if (submitBtn) submitBtn.disabled = true;
			post('gppb_admin_save_prn', data, function (err) {
				if (submitBtn) submitBtn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});

		var resetBtn = document.getElementById('gppb-prn-reset-form');
		if (resetBtn) resetBtn.addEventListener('click', function () {
			prnForm.reset();
			prnForm.querySelector('[name="id"]').value = '';
			prnForm.querySelector('[name="prn_status"]').value = 'allowed';
		});
	}

	document.querySelectorAll('.gppb-prn-edit').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!prnForm) return;
			prnForm.querySelector('[name="id"]').value = btn.dataset.id || '';
			prnForm.querySelector('[name="prn"]').value = btn.dataset.prn || '';
			prnForm.querySelector('[name="name"]').value = btn.dataset.name || '';
			prnForm.querySelector('[name="phone"]').value = btn.dataset.phone || '';
			prnForm.querySelector('[name="email"]').value = btn.dataset.email || '';
			prnForm.querySelector('[name="valid_from"]').value = btn.dataset.valid_from || '';
			prnForm.querySelector('[name="valid_until"]').value = btn.dataset.valid_until || '';
			prnForm.querySelector('[name="prn_status"]').value = btn.dataset.status || 'allowed';
			prnForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	});

	document.querySelectorAll('.gppb-prn-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirmAction()) return;
			btn.disabled = true;
			post('gppb_admin_toggle_prn', { id: btn.dataset.id, status: btn.dataset.status }, function (err) {
				btn.disabled = false;
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
				location.reload();
			});
		});
	});

	/* PRN override grant (from PRN Master row or roster) */
	document.querySelectorAll('.gppb-prn-grant-override').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = prompt('PRN:', btn.dataset.prn || '');
			if (input === null) return;
			var prnVal = input.trim();
			var date = prompt('Date (YYYY-MM-DD):');
			if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) { flash(i18n.error, false); return; }
			var chapter = prompt('Adhyaya number (1-18):');
			if (!chapter) { flash(i18n.error, false); return; }
			var slotType = window.confirm('Is this for Weekly Parayan? OK=Weekly, Cancel=Daily') ? 'weekly' : 'daily';
			post('gppb_admin_grant_prn_override', {
				sadhak_prn: prnVal,
				sadhak_name: btn.dataset.name || '',
				slot_type: slotType,
				date: date,
				adhyaya_number: chapter
			}, function (err) {
				if (err) { flash(err, false); return; }
				flash(i18n.saved, true);
			});
		});
	});
})();
