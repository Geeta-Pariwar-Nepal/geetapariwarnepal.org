/**
 * GPN CRM - admin interface.
 *
 * Drives every CRM page over admin-ajax.php. Mirrors the desktop
 * application's behavior: searchable/sortable sadhak grid, auto PRN
 * lookup, group role-holder auto-fill, history, settings, users, logs,
 * backups and sync. All operations are AJAX with no page reloads.
 */
(function ($) {
	'use strict';

	if (typeof gpnCrm === 'undefined') {
		return;
	}

	/* ───────────────────────── helpers ───────────────────────── */

	function esc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

	function fmtDate(s) {
		if (!s) { return '—'; }
		var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(s));
		if (m) {
			return m[3] + ' ' + MONTHS[(parseInt(m[2], 10) - 1)] + ' ' + m[1];
		}
		return String(s);
	}

	function toast(msg, type) {
		var t = $('#gpnToast');
		if (!t.length) {
			t = $('<div class="gpn-toast" id="gpnToast"></div>').appendTo('body');
		}
		t.removeClass('gpn-toast-success gpn-toast-error').addClass(type === 'error' ? 'gpn-toast-error' : 'gpn-toast-success');
		t.html(msg).addClass('is-visible');
		clearTimeout(t.data('timer'));
		t.data('timer', setTimeout(function () { t.removeClass('is-visible'); }, 4000));
	}

	function gpnGet(action, data) {
		return $.ajax({
			url: gpnCrm.ajaxUrl,
			method: 'GET',
			data: $.extend({}, data, { action: 'gpn_crm', gpn_action: action, nonce: gpnCrm.nonce }),
			dataType: 'json'
		});
	}

	function gpnPost(action, data) {
		return $.ajax({
			url: gpnCrm.ajaxUrl,
			method: 'POST',
			data: $.extend({}, data, { action: 'gpn_crm', gpn_action: action, nonce: gpnCrm.nonce }),
			dataType: 'json'
		});
	}

	function failHandler(xhr, fallback) {
		var res = xhr.responseJSON || {};
		if (res.relogin) { window.location.reload(); return; }
		toast(res.error || (fallback || 'Request failed (HTTP ' + xhr.status + ').'), 'error');
	}

	function openModal(id) { $('#' + id).addClass('is-open'); }
	function closeModal(id) { $('#' + id).removeClass('is-open'); }

	/* ───────────────────────── dashboard ─────────────────────── */

	function initDashboard() {
		if ($('#gpnStatTotal').length === 0) { return; }
		gpnGet('stats').done(function (res) {
			if (!res || res.error || !res.stats) { return; }
			var s = res.stats;
			$('#gpnStatTotal').text(numberFormat(s.total_sadhaks));
			$('#gpnStatToday').text(numberFormat(s.today_added));
			$('#gpnStatReady').text(numberFormat(s.ready));
			$('#gpnStatGroups').text(numberFormat(s.groups));
			$('#gpnStatActiveGroups').text(numberFormat(s.active_groups));
		}).fail(function (xhr) { failHandler(xhr); });
	}

	function numberFormat(n) {
		return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	/* ───────────────────────── sadhak grid ───────────────────── */

	function initSadhakGrid() {
		if ($('#gpnSadhakTable').length === 0) { return; }

		var state = {
			page: 1,
			search: '',
			groupId: 0,
			orderby: 'id',
			order: 'DESC',
			selectedId: 0,
			selectedRow: null,
			perPage: 50
		};

		function load() {
			var dfd = gpnGet('list_sadhaks', {
				search: state.search,
				group_id: state.groupId,
				page: state.page,
				per_page: state.perPage,
				orderby: state.orderby,
				order: state.order
			});
			dfd.done(function (res) {
				if (!res || res.error) { return; }
				render(res);
			}).fail(function (xhr) { failHandler(xhr); });
		}

		function render(res) {
			var body = $('#gpnSadhakBody').empty();
			var rows = res.records || [];
			if (!rows.length) {
				body.append('<tr><td colspan="15" class="gpn-empty">No sadhaks found.</td></tr>');
			} else {
				$.each(rows, function (i, r) {
					var sel = state.selectedId && r.id === state.selectedId ? ' selected' : '';
					var tr = $('<tr class="gpn-sadhak-row' + sel + '" data-id="' + r.id + '" data-phone="' + esc(r.phone) + '" data-name="' + esc(r.name) + '"></tr>');
					tr.append(
						'<td>' + esc(r.name) + '</td>' +
						'<td>' + esc(r.phone) + '</td>' +
						'<td>' + esc(r.email) + '</td>' +
						'<td>' + esc(r.prn) + '</td>' +
						'<td>' + esc(r.group_name) + '</td>' +
						'<td>' + esc(r.level) + '</td>' +
						'<td>' + esc(r.batch) + '</td>' +
						'<td>' + esc(r.bc_name) + '</td>' +
						'<td>' + esc(r.gc_name) + '</td>' +
						'<td>' + esc(r.ct_name) + '</td>' +
						'<td>' + esc(r.ta_name) + '</td>' +
						'<td>' + fmtDate(r.created_at) + '</td>' +
						'<td>' + fmtDate(r.updated_at) + '</td>' +
						'<td>' + esc(r.created_by_name) + '</td>' +
						'<td>' + esc(r.updated_by_name) + '</td>'
					);
					body.append(tr);
				});
			}

			state.total = res.total || 0;
			state.perPage = res.per_page || state.perPage;
			state.page = res.page || 1;
			var totalPages = Math.max(1, Math.ceil(state.total / state.perPage));

			$('#gpnTotalLabel').text('Showing ' + (rows.length ? ((state.page - 1) * state.perPage + 1) + '–' + ((state.page - 1) * state.perPage + rows.length) : 0) + ' of ' + numberFormat(state.total) + ' sadhaks');
			$('#gpnPageInfo').text('Page ' + state.page + ' of ' + totalPages);
			$('#gpnPrevPage').prop('disabled', state.page <= 1);
			$('#gpnNextPage').prop('disabled', state.page >= totalPages);
		}

		function selected() {
			if (state.selectedId) {
				return $('.gpn-sadhak-row[data-id="' + state.selectedId + '"]');
			}
			return $();
		}

		function editSelected() {
			if (!state.selectedId) { toast('Select a sadhak first.', 'error'); return; }
			window.location.href = gpnCrm.adminUrl + 'admin.php?page=gpn-crm-add&id=' + state.selectedId;
		}

		function whatsappSelected() {
			if (!state.selectedId) { toast('Select a sadhak first.', 'error'); return; }
			var el = selected();
			var phone = $(el).attr('data-phone') || '';
			var digits = phone.replace(/\D/g, '');
			if (!digits) { toast('This record has no phone number.', 'error'); return; }
			if (phone.indexOf('+') !== 0) {
				digits = String(gpnCrm.whatsappPrefix || '+977').replace(/\D/g, '') + digits;
			}
			window.open('https://wa.me/' + digits, '_blank');
		}

		$('#gpnSadhakBody').on('click', '.gpn-sadhak-row', function () {
			var id = parseInt($(this).attr('data-id'), 10);
			state.selectedId = id;
			$('.gpn-sadhak-row').removeClass('selected');
			$(this).addClass('selected');
		});

		$('#gpnSadhakBody').on('dblclick', '.gpn-sadhak-row', editSelected);

		$('#gpnEditBtn').on('click', editSelected);
		$('#gpnWhatsappBtn').on('click', whatsappSelected);
		$('#gpnRefreshBtn').on('click', load);

		$('#gpnDeleteBtn').on('click', function () {
			if (!state.selectedId) { toast('Select a sadhak first.', 'error'); return; }
			if (!confirm('Delete the selected sadhak? This cannot be undone.')) { return; }
			gpnPost('delete_sadhak', { id: state.selectedId })
				.done(function (res) {
					if (res && res.error) { toast(res.error, 'error'); return; }
					toast(res && res.message ? res.message : 'Deleted.');
					state.selectedId = 0;
					load();
				})
				.fail(failHandler);
		});

		$('#gpnHistoryBtn').on('click', function () {
			if (!state.selectedId) { toast('Select a sadhak first.', 'error'); return; }
			openHistoryModal(state.selectedId, selected().attr('data-name'));
		});

		// Sorting.
		$('#gpnSadhakTable th[data-orderby]').on('click', function () {
			var ob = $(this).attr('data-orderby');
			if (state.orderby === ob) {
				state.order = state.order === 'ASC' ? 'DESC' : 'ASC';
			} else {
				state.orderby = ob;
				state.order = 'ASC';
			}
			$('#gpnSadhakTable th').removeClass('gpn-sort-asc gpn-sort-desc');
			$(this).addClass(state.order === 'ASC' ? 'gpn-sort-asc' : 'gpn-sort-desc');
			state.page = 1;
			load();
		});

		// Pagination.
		$('#gpnPrevPage').on('click', function () { if (state.page > 1) { state.page--; load(); } });
		$('#gpnNextPage').on('click', function () {
			var totalPages = Math.ceil((state.total || 0) / state.perPage);
			if (state.page < totalPages) { state.page++; load(); }
		});

		// Search + filter.
		var searchTimer = null;
		$('#gpnSearchInput').on('input', function () {
			clearTimeout(searchTimer);
			var val = $(this).val();
			searchTimer = setTimeout(function () {
				state.search = val;
				state.page = 1;
				load();
			}, 400);
		});

		$('#gpnFilterGroup').on('change', function () {
			state.groupId = parseInt($(this).val(), 10) || 0;
			state.page = 1;
			load();
		});

		$('#gpnClearFilterBtn').on('click', function () {
			$('#gpnSearchInput').val('');
			$('#gpnFilterGroup').val('');
			state.search = '';
			state.groupId = 0;
			state.page = 1;
			load();
		});

		load();
	}

	/* ───────────────────────── history modal ─────────────────── */

	function openHistoryModal(sadhakId, name) {
		$('#gpnHistoryTitle').text('History: ' + (name || 'Sadhak #' + sadhakId));
		var body = $('#gpnHistoryBody').html('<tr><td colspan="10" class="gpn-empty">Loading...</td></tr>');
		openModal('gpnHistoryModal');
		gpnGet('sadhak_history', { id: sadhakId }).done(function (res) {
			body.empty();
			if (!res || res.error) { return; }
			var rows = res.history || [];
			if (!rows.length) {
				body.append('<tr><td colspan="10" class="gpn-empty">No history yet.</td></tr>');
				return;
			}
			$.each(rows, function (i, h) {
				body.append(
					'<tr>' +
					'<td>' + (i + 1) + '</td>' +
					'<td>' + esc(h.group_name) + '</td>' +
					'<td>' + esc(h.level) + '</td>' +
					'<td>' + esc(h.batch) + '</td>' +
					'<td>' + esc(h.bc_name) + '</td>' +
					'<td>' + esc(h.gc_name) + '</td>' +
					'<td>' + esc(h.ct_name) + '</td>' +
					'<td>' + esc(h.ta_name) + '</td>' +
					'<td>' + esc(h.changed_by_name) + '</td>' +
					'<td>' + fmtDate(h.changed_at) + '</td>' +
					'</tr>'
				);
			});
		}).fail(function (xhr) { failHandler(xhr); });
	}

	/* ───────────────────────── add / edit form ───────────────── */

	function initSadhakForm() {
		if ($('#gpnSadhakForm').length === 0) { return; }

		var groupCache = {};
		var editingId = 0;
		var searchTimer = null;

		function setRoleHolders(g) {
			$('#gpnBcDisplay').text(g && g.bc_name ? g.bc_name : '—');
			$('#gpnGcDisplay').text(g && g.gc_name ? g.gc_name : '—');
			$('#gpnCtDisplay').text(g && g.ct_name ? g.ct_name : '—');
			$('#gpnTaDisplay').text(g && g.ta_name ? g.ta_name : '—');
			if (g) {
				$('#gpnLevelDisplay').text(g.level || 'Level 1');
				$('#gpnBatchDisplay').text(g.batch || 'Regular');
				if (g.zoom_link) {
					$('#gpnZoomBtn').prop('disabled', false).attr('data-link', g.zoom_link);
				} else {
					$('#gpnZoomBtn').prop('disabled', true).removeAttr('data-link');
				}
			} else {
				$('#gpnLevelDisplay').text('');
				$('#gpnBatchDisplay').text('');
				$('#gpnZoomBtn').prop('disabled', true).removeAttr('data-link');
			}
		}

		function refreshGroup(gid) {
			gid = parseInt(gid, 10) || 0;
			if (!gid) { setRoleHolders(null); return; }
			if (groupCache[gid]) { setRoleHolders(groupCache[gid]); return; }
			gpnGet('get_group', { id: gid }).done(function (res) {
				if (res && res.group) {
					groupCache[gid] = res.group;
					setRoleHolders(res.group);
				}
			}).fail(function () {});
		}

		// Group change -> role holders + level/batch + zoom.
		$('#gpnGroup').on('change', function () {
			var opt = $(this).find('option:selected');
			$('#gpnLevelDisplay').text(opt.attr('data-level') || '');
			$('#gpnBatchDisplay').text(opt.attr('data-batch') || '');
			refreshGroup($(this).val());
		});

		$('#gpnZoomBtn').on('click', function () {
			var link = $(this).attr('data-link');
			if (link) { window.open(link, '_blank'); }
		});

		// Auto PRN search (debounced) mirroring the desktop lookup exactly:
		// _strip_country_code() then, when the remaining digits are >= 7,
		// search local DB first, LearnGeeta remote fallback, fill PRN + name.

		function setFormStatus(msg, busy) {
			$('#gpnFormStatusText').text(msg || '');
			$('#gpnFormSpinner').toggle(busy ? true : false);
		}

		function stripCountryCode(phone) {
			var raw = $.trim(phone);
			var hasIntl = raw.charAt(0) === '+' || raw.indexOf('00') === 0;
			var digits = (raw.match(/\d/g) || []).join('');
			if (raw.indexOf('00') === 0) { digits = digits.slice(2); }

			var selCode = ($('#gpnCountryCode').val() || '').replace('+', '');
			if (selCode && digits.indexOf(selCode) === 0 && digits.length > selCode.length) {
				return digits.slice(selCode.length);
			}
			if (hasIntl) {
				var codes = (gpnCrm.countryCodes || []).slice().sort(function (a, b) { return b.length - a.length; });
				for (var i = 0; i < codes.length; i++) {
					var c = codes[i].replace('+', '');
					if (digits.indexOf(c) === 0 && digits.length > c.length) {
						return digits.slice(c.length);
					}
				}
			}
			return digits;
		}

		function doPrnSearch() {
			var number = $.trim($('#gpnPhone').val());
			var digits = stripCountryCode(number);
			if (!digits || digits.length < 7 || !/^\d+$/.test(digits)) {
				$('#gpnPrn').val('');
				setFormStatus('Ready', false);
				return;
			}
			// Send only the stripped local digits (e.g. 9864026061), never a
			// country-coded number like +9779864026061. Mirrors the desktop
			// _lookup_prn(stripped_phone) call.
			setFormStatus('Searching PRN…', true);
			gpnGet('prn_search', { term: digits }).done(function (res) {
				setFormStatus('Ready', false);
				if (!res || res.error) { return; }
				var best = res.best || (res.name ? { prn: res.prn, name: res.name } : null);
				if (best && best.prn) {
					$('#gpnPrn').val(best.prn || '');
					if (!$.trim($('#gpnName').val())) {
						$('#gpnName').val(best.name || '');
					}
					setFormStatus('PRN found: ' + best.prn + ' (' + best.name + ')', false);
				} else {
					setFormStatus('No record found.', false);
				}
			}).fail(function (xhr) {
				setFormStatus('Ready', false);
				failHandler(xhr);
			});
		}

		$('#gpnPhone').on('input', function () {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(doPrnSearch, 600);
		});

		// Load existing sadhak for editing (?id=...).
		var params = new URLSearchParams(window.location.search);
		if (params.get('id')) {
			editingId = parseInt(params.get('id'), 10) || 0;
			$('#gpnFormTitle').text('Edit Sadhak #' + editingId);
			$('#gpnSaveBtn').text('Update Sadhak');
			gpnGet('get_sadhak', { id: editingId }).done(function (res) {
				if (!res || res.error) { return; }
				var s = res.sadhak;
				if (!s) { return; }
				$('#gpnName').val(s.name || '');
				var phone = s.phone || '';
				var code = gpnCrm.defaultCountry;
				var number = phone;
				if (phone.charAt(0) === '+') {
					var codes = (gpnCrm.countryCodes || []).slice().sort(function (a, b) { return b.length - a.length; });
					for (var i = 0; i < codes.length; i++) {
						if (phone.indexOf(codes[i]) === 0) {
							code = codes[i];
							number = phone.slice(codes[i].length);
							break;
						}
					}
				}
				$('#gpnCountryCode').val(code);
				$('#gpnPhone').val(number);
				$('#gpnEmail').val(s.email || '');
				$('#gpnPrn').val(s.prn || '');
				$('#gpnGroup').val(s.group_id || '');
				if (s.can_edit === false) {
					$('#gpnSaveBtn').prop('disabled', true);
					setFormStatus('You can only edit sadhaks in groups where you are BC, GC, CT or TA.', false);
				}
				$('#gpnGroup').trigger('change');
			}).fail(function (xhr) { failHandler(xhr); });
		}

		$('#gpnClearBtn').on('click', function () {
			$('#gpnSadhakForm')[0].reset();
			$('#gpnEditingId').val('');
			$('#gpnFormTitle').text('Register / Edit Sadhak');
			$('#gpnSaveBtn').text('Save Sadhak').prop('disabled', false);
			setRoleHolders(null);
			setFormStatus('Ready', false);
			window.history.replaceState({}, '', gpnCrm.adminUrl + 'admin.php?page=gpn-crm-add');
		});

		$('#gpnSadhakForm').on('submit', function (e) {
			e.preventDefault();
			var name = $.trim($('#gpnName').val());
			var number = $.trim($('#gpnPhone').val());
			if (!name || !number) { toast('Name and Mobile Number are required.', 'error'); return; }

			var data = {
				name: name,
				phone: number,
				email: $('#gpnEmail').val(),
				prn: $('#gpnPrn').val(),
				group_id: $('#gpnGroup').val() || 0,
				country_code: $('#gpnCountryCode').val(),
				status: 'Ready'
			};
			if (editingId) { data.editing_id = editingId; }

			$('#gpnSaveBtn').prop('disabled', true).text('Saving...');
			gpnPost('save_sadhak', data).done(function (res) {
				$('#gpnSaveBtn').prop('disabled', false).text(editingId ? 'Update Sadhak' : 'Save Sadhak');
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res && res.message ? res.message : 'Saved.');
				editingId = 0;
				$('#gpnSadhakForm')[0].reset();
				$('#gpnEditingId').val('');
				$('#gpnFormTitle').text('Register / Edit Sadhak');
				setRoleHolders(null);
				window.history.replaceState({}, '', gpnCrm.adminUrl + 'admin.php?page=gpn-crm-add');
			}).fail(function (xhr) {
				$('#gpnSaveBtn').prop('disabled', false).text(editingId ? 'Update Sadhak' : 'Save Sadhak');
				failHandler(xhr);
			});
		});
	}

	/* ───────────────────────── groups ────────────────────────── */

	function initGroups() {
		if ($('#gpnGroupTable').length === 0) { return; }

		var selectedId = 0;
		var rows = [];

		function load() {
			gpnGet('list_groups').done(function (res) {
				if (!res || res.error) { return; }
				rows = res.groups || [];
				var body = $('#gpnGroupBody').empty();
				if (!rows.length) {
					body.append('<tr><td colspan="10" class="gpn-empty">No groups yet.</td></tr>');
					return;
				}
				$.each(rows, function (i, g) {
					var sel = selectedId && parseInt(g.id, 10) === selectedId ? ' selected' : '';
					var zoom = g.zoom_link ? '<a href="#" class="gpn-group-zoom" data-link="' + esc(g.zoom_link) + '">' + esc(g.zoom_link) + '</a>' : '—';
					var st = g.status === 'Active'
						? '<span class="gpn-badge gpn-badge-success">Active</span>'
						: '<span class="gpn-badge gpn-badge-secondary">' + esc(g.status) + '</span>';
					$('<tr class="gpn-group-row' + sel + '" data-id="' + g.id + '"></tr>').append(
						'<td><strong>' + esc(g.name) + '</strong></td>' +
						'<td>' + esc(g.level) + '</td>' +
						'<td>' + esc(g.batch) + '</td>' +
						'<td>' + esc(g.timing) + '</td>' +
						'<td>' + esc(g.bc_name) + '</td>' +
						'<td>' + esc(g.gc_name) + '</td>' +
						'<td>' + esc(g.ct_name) + '</td>' +
						'<td>' + esc(g.ta_name) + '</td>' +
						'<td>' + st + '</td>' +
						'<td>' + zoom + '</td>'
					).appendTo(body);
				});
			}).fail(function (xhr) { failHandler(xhr); });
		}

		function selected() {
			return rows.filter(function (g) { return parseInt(g.id, 10) === selectedId; })[0] || null;
		}

		function openEdit(g) {
			$('#gpnGroupEditTitle').text(g && g.id ? 'Edit Group' : 'New Group');
			$('#gpnGroupEditId').val(g ? g.id : '');
			$('#gpnGroupName').val(g ? g.name : '');
			$('#gpnGroupLevel').val(g ? g.level : 'Level 1');
			$('#gpnGroupBatch').val(g ? g.batch : 'Regular');
			$('#gpnGroupBc').val(g ? g.bc_name : '');
			$('#gpnGroupGc').val(g ? g.gc_name : '');
			$('#gpnGroupCt').val(g ? g.ct_name : '');
			$('#gpnGroupTa').val(g ? g.ta_name : '');
			$('#gpnGroupTiming').val(g ? g.timing : '');
			$('#gpnGroupZoom').val(g ? g.zoom_link : '');
			$('#gpnGroupStatus').val(g ? g.status : 'Active');
			openModal('gpnGroupEditModal');
		}

		$('#gpnGroupBody').on('click', '.gpn-group-row', function () {
			selectedId = parseInt($(this).attr('data-id'), 10);
			$('.gpn-group-row').removeClass('selected');
			$(this).addClass('selected');
		});

		$('#gpnGroupBody').on('click', '.gpn-group-zoom', function (e) {
			e.preventDefault();
			e.stopPropagation();
			window.open($(this).attr('data-link'), '_blank');
		});

		$('#gpnAddGroupBtn').on('click', function () { openEdit(null); });
		$('#gpnEditGroupBtn').on('click', function () {
			var g = selected();
			if (!g) { toast('Select a group first.', 'error'); return; }
			openEdit(g);
		});

		$('#gpnDeleteGroupBtn').on('click', function () {
			var g = selected();
			if (!g) { toast('Select a group first.', 'error'); return; }
			if (!confirm('Delete group "' + g.name + '"? Sadhaks in it will keep their records but lose the group link.')) { return; }
			gpnPost('delete_group', { id: g.id }).done(function (res) {
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res && res.message ? res.message : 'Deleted.');
				selectedId = 0;
				load();
			}).fail(failHandler);
		});

		$('#gpnOpenGroupZoomBtn').on('click', function () {
			var g = selected();
			if (!g) { toast('Select a group first.', 'error'); return; }
			if (!g.zoom_link) { toast('This group has no Zoom link.', 'error'); return; }
			window.open(g.zoom_link, '_blank');
		});

		$('#gpnRefreshGroupsBtn').on('click', load);

		$('#gpnGroupForm').on('submit', function (e) {
			e.preventDefault();
			var data = {
				id: parseInt($('#gpnGroupEditId').val(), 10) || 0,
				name: $('#gpnGroupName').val(),
				level: $('#gpnGroupLevel').val(),
				batch: $('#gpnGroupBatch').val(),
				bc_name: $('#gpnGroupBc').val(),
				gc_name: $('#gpnGroupGc').val(),
				ct_name: $('#gpnGroupCt').val(),
				ta_name: $('#gpnGroupTa').val(),
				timing: $('#gpnGroupTiming').val(),
				zoom_link: $('#gpnGroupZoom').val(),
				status: $('#gpnGroupStatus').val()
			};
			gpnPost('save_group', data).done(function (res) {
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res && res.message ? res.message : 'Group saved.');
				closeModal('gpnGroupEditModal');
				selectedId = 0;
				load();
			}).fail(failHandler);
		});

		load();
	}

	/* ───────────────────────── users + logs ──────────────────── */

	function initUsers() {
		if ($('#gpnUserTable').length === 0) { return; }

		var selectedId = 0;

		function roleBadge(role) {
			var cls = 'gpn-badge-secondary';
			if (role === 'Admin') { cls = 'gpn-badge-danger'; }
			else if (role === 'BC') { cls = 'gpn-badge-blue'; }
			else if (role === 'GC') { cls = 'gpn-badge-info'; }
			else if (role === 'CT' || role === 'TA') { cls = 'gpn-badge-warning'; }
			return '<span class="gpn-badge ' + cls + '">' + esc(role) + '</span>';
		}

		function load() {
			gpnGet('list_users').done(function (res) {
				if (!res || res.error) { return; }
				var body = $('#gpnUserBody').empty();
				var users = res.users || [];
				if (!users.length) {
					body.append('<tr><td colspan="6" class="gpn-empty">No users yet.</td></tr>');
					return;
				}
				$.each(users, function (i, u) {
					var sel = selectedId && u.id === selectedId ? ' selected' : '';
					var active = u.active ? '<span class="gpn-badge gpn-badge-success">Active</span>' : '<span class="gpn-badge gpn-badge-secondary">Inactive</span>';
					$('<tr class="gpn-user-row' + sel + '" data-id="' + u.id + '"></tr>').append(
						'<td><strong>' + esc(u.full_name) + '</strong></td>' +
						'<td>' + esc(u.email || '—') + '</td>' +
						'<td>' + esc(u.username) + '</td>' +
						'<td>' + roleBadge(u.role) + '</td>' +
						'<td>' + active + '</td>' +
						'<td>' + fmtDate(u.created_at) + '</td>' +
						'<td>' + fmtDate(u.updated_at) + '</td>'
					).appendTo(body);
				});
			}).fail(function (xhr) { failHandler(xhr); });
		}

		function selectedUser() {
			var found = null;
			$('#gpnUserBody .gpn-user-row').each(function () {
				if (parseInt($(this).attr('data-id'), 10) === selectedId) {
					var cells = $(this).children('td');
					found = {
						id: selectedId,
						full_name: $.trim(cells.eq(0).text()),
						email: $.trim(cells.eq(1).text()),
						username: $.trim(cells.eq(2).text()),
						role: $.trim($(cells.eq(3)).text()),
						active: $.trim(cells.eq(4).text()) === 'Active' ? 1 : 0
					};
				}
			});
			return found;
		}

		function openUserModal(u) {
			$('#gpnUserModalTitle').text(u && u.id ? 'Edit User' : 'Add User');
			$('#gpnUserId').val(u ? u.id : '');
			$('#gpnUserFullName').val(u ? u.full_name : '');
			$('#gpnUserEmail').val(u ? (u.email || '') : '');
			$('#gpnUserUsername').val(u ? u.username : '');
			$('#gpnUserPassword').val('');
			$('#gpnUserConfirmPassword').val('');
			$('#gpnUserRole').val(u ? u.role : 'BC');
			$('#gpnUserActive').prop('checked', u ? !!u.active : true);
			openModal('gpnUserModal');
		}

		function validateUserForm() {
			var fullName = $.trim($('#gpnUserFullName').val());
			var email = $.trim($('#gpnUserEmail').val());
			var username = $.trim($('#gpnUserUsername').val());
			var password = $('#gpnUserPassword').val();
			var confirmPassword = $('#gpnUserConfirmPassword').val();
			var role = $('#gpnUserRole').val();
			var editingId = parseInt($('#gpnUserId').val(), 10) || 0;

			if (!fullName) {
				toast('Full name is required.', 'error');
				$('#gpnUserFullName').focus();
				return false;
			}
			if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
				toast('A valid email address is required.', 'error');
				$('#gpnUserEmail').focus();
				return false;
			}
			if (!username) {
				toast('Username is required.', 'error');
				$('#gpnUserUsername').focus();
				return false;
			}
			if (!editingId && !password) {
				toast('Password is required for new users.', 'error');
				$('#gpnUserPassword').focus();
				return false;
			}
			if (password && password !== confirmPassword) {
				toast('Passwords do not match.', 'error');
				$('#gpnUserConfirmPassword').focus();
				return false;
			}
			if (!role) {
				toast('Role is required.', 'error');
				$('#gpnUserRole').focus();
				return false;
			}
			return true;
		}

		$('#gpnUserBody').on('click', '.gpn-user-row', function () {
			selectedId = parseInt($(this).attr('data-id'), 10);
			$('.gpn-user-row').removeClass('selected');
			$(this).addClass('selected');
		});

		$('#gpnAddUserBtn').on('click', function () { openUserModal(null); });
		$('#gpnEditUserBtn').on('click', function () {
			var u = selectedUser();
			if (!u) { toast('Select a user first.', 'error'); return; }
			openUserModal(u);
		});
		$('#gpnRefreshUsersBtn').on('click', load);

		$('#gpnDeleteUserBtn').on('click', function () {
			var u = selectedUser();
			if (!u) { toast('Select a user first.', 'error'); return; }
			if (u.role === 'Admin') {
				var adminCount = $('#gpnUserBody .gpn-user-row').filter(function () {
					return $(this).find('td:eq(2)').text().trim() === 'Administrator';
				}).length;
				if (adminCount <= 1) {
					toast('Cannot delete the last Administrator.', 'error');
					return;
				}
			}
			if (!confirm('Are you sure you want to delete this CRM user?')) { return; }
			gpnPost('delete_user', { id: u.id }).done(function (res) {
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res && res.message ? res.message : 'User deleted.');
				selectedId = 0;
				load();
			}).fail(failHandler);
		});

		$('#gpnUserForm').on('submit', function (e) {
			e.preventDefault();
			if (!validateUserForm()) { return; }
			var data = {
				id: parseInt($('#gpnUserId').val(), 10) || 0,
				full_name: $('#gpnUserFullName').val(),
				email: $('#gpnUserEmail').val(),
				username: $('#gpnUserUsername').val(),
				password: $('#gpnUserPassword').val(),
				role: $('#gpnUserRole').val(),
				active: $('#gpnUserActive').is(':checked') ? 1 : 0
			};
			$('#gpnSaveUserBtn').prop('disabled', true).text('Saving...');
			gpnPost('save_user', data).done(function (res) {
				$('#gpnSaveUserBtn').prop('disabled', false).text('Save User');
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res && res.message ? res.message : 'User saved.');
				closeModal('gpnUserModal');
				selectedId = 0;
				load();
			}).fail(function (xhr) {
				$('#gpnSaveUserBtn').prop('disabled', false).text('Save User');
				failHandler(xhr);
			});
		});

		// Logs.
		function loadLogs() {
			gpnGet('logs', { limit: 50 }).done(function (res) {
				if (!res || res.error) { return; }
				var body = $('#gpnLogBody').empty();
				var logs = res.logs || [];
				if (!logs.length) {
					body.append('<tr><td colspan="6" class="gpn-empty">No activity yet.</td></tr>');
					return;
				}
				$.each(logs, function (i, l) {
					body.append(
						'<tr>' +
						'<td>' + esc(l.user_name || 'System') + '</td>' +
						'<td><span class="gpn-badge gpn-badge-info">' + esc(l.action) + '</span></td>' +
						'<td>' + esc(l.entity) + '</td>' +
						'<td>' + esc(l.description) + '</td>' +
						'<td>' + esc(l.ip) + '</td>' +
						'<td>' + fmtDate(l.created_at) + '</td>' +
						'</tr>'
					);
				});
			}).fail(function (xhr) { failHandler(xhr); });
		}

		$('#gpnRefreshLogsBtn').on('click', loadLogs);
		$('#gpnClearLogsBtn').on('click', function () {
			if (!confirm('Clear all activity logs?')) { return; }
			gpnPost('logs_clear').done(function (res) {
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast('Logs cleared.');
				loadLogs();
			}).fail(failHandler);
		});

		load();
		loadLogs();
	}

	/* ───────────────────────── settings ──────────────────────── */

	function initSettings() {
		if ($('#gpnSettingsForm').length === 0) { return; }

		$('#gpnRegenToken').on('click', function () {
			var token = '';
			var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			for (var i = 0; i < 40; i++) { token += chars.charAt(Math.floor(Math.random() * chars.length)); }
			$('#gpnSetSyncToken').val(token);
		});

		$('#gpnSettingsForm').on('submit', function (e) {
			e.preventDefault();
			var data = {
				app_name: $('#gpnSetAppName').val(),
				default_country: $('#gpnSetCountry').val(),
				per_page: parseInt($('#gpnSetPerPage').val(), 10) || 50,
				prn_remote_search: $('#gpnSetPrnRemote').is(':checked') ? 1 : 0,
				prn_remote_timeout: parseInt($('#gpnSetPrnTimeout').val(), 10) || 3,
				auto_backup: $('#gpnSetAutoBackup').is(':checked') ? 1 : 0,
				keep_backups: parseInt($('#gpnSetKeepBackups').val(), 10) || 20,
				whatsapp_enabled: $('#gpnSetWhatsapp').is(':checked') ? 1 : 0,
				whatsapp_prefix: $('#gpnSetWhatsappPrefix').val(),
				sync_enabled: $('#gpnSetSyncEnabled').is(':checked') ? 1 : 0,
				sync_token: $('#gpnSetSyncToken').val(),
				date_format: 'Y-m-d H:i',
				log_history: 1
			};
			$('#gpnSettingsStatus').text('Saving...');
			gpnPost('settings_save', data).done(function (res) {
				if (res && res.error) { $('#gpnSettingsStatus').text(res.error); return; }
				$('#gpnSettingsStatus').text('Settings saved.');
				toast(res && res.message ? res.message : 'Settings saved.');
			}).fail(function (xhr) {
				$('#gpnSettingsStatus').text('Failed to save settings.');
				failHandler(xhr);
			});
		});
	}

	/* ───────────────────────── sync ──────────────────────────── */

	function initSync() {
		if ($('#gpnSyncPullBtn').length === 0) { return; }

		function run(mode) {
			var url = $.trim($('#gpnSyncUrl').val());
			if (!url) { toast('Enter the remote WordPress URL.', 'error'); return; }
			var label = mode === 'pull' ? 'Pull' : 'Push';
			if (!confirm(label + ' data with the remote site? Pulling replaces this site\'s data (a safety backup is created first).')) { return; }
			$('#gpnSyncStatus').text(label + 'ing...');
			gpnPost('sync', {
				mode: mode,
				url: url,
				username: $('#gpnSyncUsername').val(),
				password: $('#gpnSyncPassword').val(),
				token: $('#gpnSyncToken').val()
			}).done(function (res) {
				if (res && res.error) {
					$('#gpnSyncStatus').text('Error: ' + res.error);
					toast(res.error, 'error');
					return;
				}
				$('#gpnSyncStatus').text(res.message || 'Sync complete.');
				toast(res.message || 'Sync complete.');
			}).fail(function (xhr) {
				$('#gpnSyncStatus').text('Sync failed.');
				failHandler(xhr);
			});
		}

		$('#gpnSyncPullBtn').on('click', function () { run('pull'); });
		$('#gpnSyncPushBtn').on('click', function () { run('push'); });
	}

	/* ───────────────────────── backup ────────────────────────── */

	function initBackup() {
		if ($('#gpnBackupTable').length === 0) { return; }

		$('#gpnCreateBackupBtn').on('click', function () {
			$('#gpnBackupStatus').text('Creating backup...');
			gpnPost('backup_create').done(function (res) {
				if (res && res.error) {
					$('#gpnBackupStatus').text('Error: ' + res.error);
					return;
				}
				$('#gpnBackupStatus').text(res.message || 'Backup created.');
				toast(res.message || 'Backup created.');
				setTimeout(function () { window.location.reload(); }, 900);
			}).fail(function (xhr) {
				$('#gpnBackupStatus').text('Failed to create backup.');
				failHandler(xhr);
			});
		});

		$('#gpnBackupBody').on('click', '.gpn-restore-backup', function () {
			var name = $(this).attr('data-name');
			if (!confirm('Restore from "' + name + '"? This replaces the current data (a safety backup is created first).')) { return; }
			gpnPost('backup_restore', { name: name }).done(function (res) {
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res.message || 'Restored.');
				setTimeout(function () { window.location.reload(); }, 900);
			}).fail(failHandler);
		});

		$('#gpnBackupBody').on('click', '.gpn-delete-backup', function () {
			var name = $(this).attr('data-name');
			if (!confirm('Delete backup "' + name + '"?')) { return; }
			gpnPost('backup_delete', { name: name }).done(function (res) {
				if (res && res.error) { toast(res.error, 'error'); return; }
				toast(res.message || 'Deleted.');
				setTimeout(function () { window.location.reload(); }, 600);
			}).fail(failHandler);
		});
	}

	/* ───────────────────────── import ─────────────────────────── */

	var IMPORT_TARGETS = [
		{ v: 'ignore', l: '— Ignore —' },
		{ v: 'name', l: 'Name' },
		{ v: 'phone', l: 'Phone / Mobile' },
		{ v: 'email', l: 'Email' },
		{ v: 'prn', l: 'PRN' },
		{ v: 'group_name', l: 'Group' },
		{ v: 'level', l: 'Level' },
		{ v: 'batch', l: 'Batch' },
		{ v: 'bc_name', l: 'BC' },
		{ v: 'gc_name', l: 'GC' },
		{ v: 'ct_name', l: 'CT' },
		{ v: 'ta_name', l: 'TA' },
		{ v: 'status', l: 'Status' }
	];

	function initImport() {
		if ($('#gpnImportForm').length === 0) { return; }

		var preview = null;
		var mapping = {};
		var logText = '';

		function setStatus(msg, busy) {
			$('#gpnImportStatusText').text(msg || '');
			$('#gpnImportSpinner').toggle(busy ? true : false);
		}

		function importBusy(busy) {
			$('#gpnImportPreviewBtn').prop('disabled', busy);
			$('#gpnImportRunBtn').prop('disabled', busy);
		}

		function showStep(step) {
			$('#gpnImportStepUpload').toggle(step === 'upload');
			$('#gpnImportStepPreview').toggle(step === 'preview');
			$('#gpnImportStepReport').toggle(step === 'report');
		}

		function sampleValue(row, col, idx) {
			if ($.isArray(row)) { return row[idx] === undefined ? '' : row[idx]; }
			return row[col] === undefined ? '' : row[col];
		}

		function buildMappingUI() {
			var cols = preview.columns || preview.headers || [];
			var $box = $('#gpnImportMapping').empty();
			mapping = {};
			$.each(cols, function (idx, col) {
				var cur = (preview.mapping && preview.mapping[idx]) || 'ignore';
				mapping[idx] = cur;
				var $sel = $('<select>');
				$.each(IMPORT_TARGETS, function (i, t) {
					$('<option>').val(t.v).text(t.l).appendTo($sel);
				});
				$sel.val(cur).on('change', function () { mapping[idx] = $(this).val(); });
				$('<div class="gpn-map-row">')
					.append($('<span class="gpn-map-col">').text(col))
					.append($sel)
					.appendTo($box);
			});

			var $samples = $('#gpnImportSamples').empty();
			var rows = preview.samples || [];
			if (rows.length) {
				var $table = $('<table class="gpn-table">');
				var $head = $('<tr>');
				$.each(cols, function (i, col) { $('<th>').text(col).appendTo($head); });
				$('<thead>').append($head).appendTo($table);
				var $tbody = $('<tbody>');
				$.each(rows, function (r, row) {
					var $tr = $('<tr>');
					$.each(cols, function (i, col) {
						$('<td>').text(sampleValue(row, col, i)).appendTo($tr);
					});
					$tbody.append($tr);
				});
				$('<caption>').text('Sample rows (first 5)').appendTo($table);
				$table.append($tbody).appendTo($samples);
			}
		}

		$('#gpnImportForm').on('submit', function (e) {
			e.preventDefault();
			var input = $('#gpnImportFile')[0];
			if (!input || !input.files || !input.files.length) {
				toast('Choose a file first.', 'error');
				return;
			}
			var fd = new FormData();
			fd.append('action', 'gpn_crm');
			fd.append('gpn_action', 'import_preview');
			fd.append('nonce', gpnCrm.nonce);
			fd.append('file', input.files[0]);
			setStatus('Reading file…', true);
			importBusy(true);
			$.ajax({
				url: gpnCrm.ajaxUrl,
				method: 'POST',
				data: fd,
				processData: false,
				contentType: false,
				dataType: 'json'
			}).done(function (res) {
				importBusy(false);
				if (!res || res.error) { setStatus('Ready', false); toast(res && res.error ? res.error : 'Preview failed.', 'error'); return; }
				preview = res.preview;
				setStatus('Ready', false);
				$('#gpnImportPreviewNote').text(
					preview.ext === 'db' || preview.ext === 'sqlite' || preview.ext === 'sqlite3'
						? 'Database: "' + preview.table + '" — ' + numberFormat(preview.total_rows) + ' rows found.'
						: numberFormat(preview.total_rows) + ' data rows found. Adjust the column mapping if needed.'
				);
				buildMappingUI();
				$('#gpnImportRunLabel').text('Import ' + numberFormat(preview.total_rows) + ' Rows');
				showStep('preview');
			}).fail(function (xhr) {
				importBusy(false);
				setStatus('Ready', false);
				failHandler(xhr, 'Could not read the file.');
			});
		});

		$('#gpnImportRunBtn').on('click', function () {
			if (!preview) { return; }
			importBusy(true);
			setStatus('Importing…', true);
			gpnPost('import_run', {
				file: preview.file,
				mapping: JSON.stringify(mapping)
			}).done(function (res) {
				importBusy(false);
				setStatus('Ready', false);
				if (!res || res.error) { toast(res && res.error ? res.error : 'Import failed.', 'error'); return; }
				var r = res.result;
				logText = r.log || '';
				var $rep = $('#gpnImportReport').empty();
				var counts =
					'<span class="gpn-badge gpn-badge-success">Imported: ' + r.added + '</span>' +
					'<span class="gpn-badge gpn-badge-blue">Updated: ' + r.updated + '</span>' +
					'<span class="gpn-badge gpn-badge-secondary">Skipped: ' + r.skipped + '</span>' +
					'<span class="gpn-badge gpn-badge-danger">Errors: ' + r.errors + '</span>';
				$('<div class="gpn-report-counts">').html(counts).appendTo($rep);
				$('<div class="gpn-report-log">').text(logText).appendTo($rep);
				showStep('report');
				toast('Import complete: ' + r.added + ' added, ' + r.updated + ' updated.');
			}).fail(function (xhr) {
				importBusy(false);
				setStatus('Ready', false);
				failHandler(xhr, 'Import failed.');
			});
		});

		$('#gpnImportLogBtn').on('click', function () {
			if (!logText) { return; }
			var blob = new Blob([logText], { type: 'text/plain;charset=utf-8' });
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = 'gpn-import-report-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.txt';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
		});

		function resetImport() {
			if (preview && preview.file) {
				gpnPost('import_discard', { file: preview.file });
			}
			preview = null;
			mapping = {};
			logText = '';
			$('#gpnImportFile').val('');
			$('#gpnImportMapping').empty();
			$('#gpnImportSamples').empty();
			$('#gpnImportReport').empty();
			setStatus('Ready', false);
			showStep('upload');
		}

		$('#gpnImportCancelBtn').on('click', resetImport);
		$('#gpnImportAgainBtn').on('click', resetImport);
	}

	/* ───────────────────────── modals + logout ───────────────── */

	function initModals() {
		$(document).on('click', '.gpn-modal-close, [data-close]', function () {
			var id = $(this).attr('data-close');
			if (id) { closeModal(id); }
		});
		$(document).on('click', '.gpn-modal-overlay', function (e) {
			if (e.target === this) { $(this).removeClass('is-open'); }
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') { $('.gpn-modal-overlay.is-open').removeClass('is-open'); }
		});

		$('#gpnLogoutBtn').on('click', function () {
			if (!confirm('Log out of the CRM?')) { return; }
			gpnPost('logout').always(function () { window.location.reload(); });
		});
	}

	/* ───────────────────────── boot ──────────────────────────── */

	$(function () {
		initModals();
		initDashboard();
		initSadhakGrid();
		initSadhakForm();
		initGroups();
		initUsers();
		initSettings();
		initSync();
		initBackup();
		initImport();

		if (!gpnCrm.isAdmin) {
			$('#gpnDeleteBtn, #gpnAddUserBtn, #gpnEditUserBtn, #gpnDeleteUserBtn').hide();
		}
	});
})(jQuery);
