(function ($) {
	'use strict';

	// ==========================================
	// Tabs
	// ==========================================
	$('.wscm-tab').on('click', function () {
		var tab = $(this).data('tab');
		$('.wscm-tab').removeClass('active');
		$(this).addClass('active');
		$('.wscm-tab-content').removeClass('active');
		$('#tab-' + tab).addClass('active');

		var hideSave = (tab === 'privacy' || tab === 'stats');
		$('#wscm-settings-form .submit').toggle(!hideSave);
	});

	// ==========================================
	// Save settings
	// ==========================================
	$('#wscm-settings-form').on('submit', function (e) {
		e.preventDefault();

		var formData = {};
		var $form = $(this);

		$form.find('input, textarea, select').each(function () {
			var $el = $(this);
			var name = $el.attr('name');
			if (!name || name === 'wscm_nonce' || name === '_wp_http_referer') return;

			var match = name.match(/^detected_scripts\[([^\]]+)\]\[([^\]]+)\]$/);
			if (match) {
				if (!formData.detected_scripts) formData.detected_scripts = {};
				if (!formData.detected_scripts[match[1]]) formData.detected_scripts[match[1]] = {};

				if ($el.attr('type') === 'checkbox') {
					formData.detected_scripts[match[1]][match[2]] = $el.is(':checked') ? '1' : '';
				} else {
					formData.detected_scripts[match[1]][match[2]] = $el.val();
				}
				return;
			}

			var csMatch = name.match(/^custom_scripts\[([^\]]+)\]\[([^\]]+)\]$/);
			if (csMatch) {
				if (!formData.custom_scripts) formData.custom_scripts = {};
				if (!formData.custom_scripts[csMatch[1]]) formData.custom_scripts[csMatch[1]] = {};

				if ($el.attr('type') === 'checkbox') {
					formData.custom_scripts[csMatch[1]][csMatch[2]] = $el.is(':checked') ? '1' : '';
				} else {
					formData.custom_scripts[csMatch[1]][csMatch[2]] = $el.val();
				}
				return;
			}

			if ($el.attr('type') === 'checkbox') {
				formData[name] = $el.is(':checked') ? '1' : '';
			} else {
				formData[name] = $el.val();
			}
		});

		var $notice = $('#wscm-save-notice');
		$notice.text('').removeClass('success error');

		$.post(wscm_admin.ajax_url, {
			action: 'wscm_save_settings',
			nonce: wscm_admin.nonce,
			settings: formData
		}).done(function (response) {
			if (response.success) {
				$notice.text(wscm_admin.strings.saved).addClass('success');
			} else {
				$notice.text(wscm_admin.strings.error).addClass('error');
			}
			setTimeout(function () { $notice.text('').removeClass('success error'); }, 3000);
		}).fail(function () {
			$notice.text(wscm_admin.strings.error).addClass('error');
			setTimeout(function () { $notice.text('').removeClass('success error'); }, 3000);
		});
	});

	// ==========================================
	// Scan
	// ==========================================
	$('#wscm-scan-btn').on('click', function () {
		var $btn = $(this);
		$btn.addClass('scanning').text(wscm_admin.strings.scanning);

		$.post(wscm_admin.ajax_url, {
			action: 'wscm_run_scan',
			nonce: wscm_admin.nonce
		}).done(function (response) {
			if (response.success) {
				pollScanResult($btn, response.data.last_scan, 0);
			} else {
				$btn.removeClass('scanning').text(wscm_admin.strings.error);
			}
		}).fail(function () {
			$btn.removeClass('scanning').text(wscm_admin.strings.error);
		});
	});

	function pollScanResult($btn, oldStamp, attempt) {
		if (attempt > 20) {
			$btn.removeClass('scanning').text(wscm_admin.strings.error);
			return;
		}

		setTimeout(function () {
			$.get(wscm_admin.ajax_url, {
				action: 'wscm_check_scan',
				nonce: wscm_admin.nonce
			}).done(function (res) {
				if (res.success && res.data.status === 'done' && res.data.last_scan !== oldStamp) {
					$btn.removeClass('scanning').html('<span class="dashicons dashicons-search"></span> ' + wscm_admin.strings.scanned);
					setTimeout(function () { location.reload(); }, 600);
				} else {
					pollScanResult($btn, oldStamp, attempt + 1);
				}
			}).fail(function () {
				pollScanResult($btn, oldStamp, attempt + 1);
			});
		}, 1500);
	}

	// ==========================================
	// Charts — lightweight canvas rendering
	// ==========================================
	var stats = wscm_admin.stats || {};

	function drawDailyChart(dailyData) {
		var canvas = document.getElementById('wscm-chart-daily');
		if (!canvas || !canvas.getContext) return;

		var ctx = canvas.getContext('2d');
		var dpr = window.devicePixelRatio || 1;
		var rect = canvas.parentElement.getBoundingClientRect();
		canvas.width = rect.width * dpr;
		canvas.height = 260 * dpr;
		canvas.style.width = rect.width + 'px';
		canvas.style.height = '260px';
		ctx.scale(dpr, dpr);

		var W = rect.width;
		var H = 260;
		var pad = { top: 20, right: 20, bottom: 40, left: 50 };

		var days = {};
		for (var i = 0; i < dailyData.length; i++) {
			var d = dailyData[i];
			if (!days[d.day]) days[d.day] = { accept_all: 0, reject_all: 0, save_preferences: 0 };
			days[d.day][d.action_type] = parseInt(d.cnt, 10);
		}

		var labels = Object.keys(days).sort();
		if (labels.length === 0) {
			ctx.fillStyle = '#94a3b8';
			ctx.font = '14px -apple-system, sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText('Nog geen data', W / 2, H / 2);
			return;
		}

		var maxVal = 0;
		for (var k = 0; k < labels.length; k++) {
			var row = days[labels[k]];
			var total = row.accept_all + row.reject_all + row.save_preferences;
			if (total > maxVal) maxVal = total;
		}
		if (maxVal === 0) maxVal = 1;

		var plotW = W - pad.left - pad.right;
		var plotH = H - pad.top - pad.bottom;
		var barGroupW = plotW / labels.length;
		var barW = Math.min(barGroupW * 0.7, 28);
		var gap = 2;
		var singleBar = (barW - gap * 2) / 3;

		var colors = { accept_all: '#22c55e', reject_all: '#ef4444', save_preferences: '#3b82f6' };
		var actionKeys = ['accept_all', 'reject_all', 'save_preferences'];

		// Grid lines
		ctx.strokeStyle = '#f1f5f9';
		ctx.lineWidth = 1;
		var gridSteps = 4;
		for (var g = 0; g <= gridSteps; g++) {
			var gy = pad.top + plotH - (plotH / gridSteps) * g;
			ctx.beginPath();
			ctx.moveTo(pad.left, gy);
			ctx.lineTo(W - pad.right, gy);
			ctx.stroke();

			ctx.fillStyle = '#94a3b8';
			ctx.font = '11px -apple-system, sans-serif';
			ctx.textAlign = 'right';
			ctx.fillText(Math.round((maxVal / gridSteps) * g), pad.left - 8, gy + 4);
		}

		// Bars
		for (var b = 0; b < labels.length; b++) {
			var x0 = pad.left + b * barGroupW + (barGroupW - barW) / 2;
			var rowData = days[labels[b]];

			for (var a = 0; a < actionKeys.length; a++) {
				var val = rowData[actionKeys[a]] || 0;
				var bh = (val / maxVal) * plotH;
				var bx = x0 + a * (singleBar + gap);
				var by = pad.top + plotH - bh;

				ctx.fillStyle = colors[actionKeys[a]];
				roundRect(ctx, bx, by, singleBar, bh, 2);
			}

			// Date label
			ctx.fillStyle = '#94a3b8';
			ctx.font = '10px -apple-system, sans-serif';
			ctx.textAlign = 'center';
			var dateLabel = labels[b].substring(5);
			ctx.fillText(dateLabel, x0 + barW / 2, H - pad.bottom + 16);
		}
	}

	function drawDonutChart(acceptAll, rejectAll, savePrefs) {
		var canvas = document.getElementById('wscm-chart-donut');
		if (!canvas || !canvas.getContext) return;

		var ctx = canvas.getContext('2d');
		var dpr = window.devicePixelRatio || 1;
		var size = 180;
		canvas.width = size * dpr;
		canvas.height = size * dpr;
		canvas.style.width = size + 'px';
		canvas.style.height = size + 'px';
		ctx.scale(dpr, dpr);

		var total = acceptAll + rejectAll + savePrefs;
		var cx = size / 2;
		var cy = size / 2;
		var r = 78;
		var inner = 50;

		if (total === 0) {
			ctx.beginPath();
			ctx.arc(cx, cy, r, 0, Math.PI * 2);
			ctx.arc(cx, cy, inner, 0, Math.PI * 2, true);
			ctx.fillStyle = '#f1f5f9';
			ctx.fill();

			ctx.fillStyle = '#94a3b8';
			ctx.font = '13px -apple-system, sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText('Geen data', cx, cy + 5);
			return;
		}

		var slices = [
			{ val: acceptAll, color: '#22c55e' },
			{ val: rejectAll, color: '#ef4444' },
			{ val: savePrefs, color: '#3b82f6' }
		];

		var startAngle = -Math.PI / 2;
		for (var i = 0; i < slices.length; i++) {
			if (slices[i].val === 0) continue;
			var sliceAngle = (slices[i].val / total) * Math.PI * 2;
			ctx.beginPath();
			ctx.arc(cx, cy, r, startAngle, startAngle + sliceAngle);
			ctx.arc(cx, cy, inner, startAngle + sliceAngle, startAngle, true);
			ctx.closePath();
			ctx.fillStyle = slices[i].color;
			ctx.fill();
			startAngle += sliceAngle;
		}

		// Center text
		ctx.fillStyle = '#1e293b';
		ctx.font = 'bold 22px -apple-system, sans-serif';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.fillText(total, cx, cy - 6);
		ctx.fillStyle = '#94a3b8';
		ctx.font = '11px -apple-system, sans-serif';
		ctx.fillText('totaal', cx, cy + 12);
	}

	function roundRect(ctx, x, y, w, h, radius) {
		if (h < 1) return;
		ctx.beginPath();
		ctx.moveTo(x + radius, y);
		ctx.lineTo(x + w - radius, y);
		ctx.quadraticCurveTo(x + w, y, x + w, y + radius);
		ctx.lineTo(x + w, y + h);
		ctx.lineTo(x, y + h);
		ctx.lineTo(x, y + radius);
		ctx.quadraticCurveTo(x, y, x + radius, y);
		ctx.closePath();
		ctx.fill();
	}

	function extractActionCounts(byAction) {
		var accept = 0, reject = 0, custom = 0;
		for (var i = 0; i < byAction.length; i++) {
			switch (byAction[i].action_type) {
				case 'accept_all': accept = parseInt(byAction[i].cnt, 10); break;
				case 'reject_all': reject = parseInt(byAction[i].cnt, 10); break;
				case 'save_preferences': custom = parseInt(byAction[i].cnt, 10); break;
			}
		}
		return { accept: accept, reject: reject, custom: custom };
	}

	function renderCharts(data) {
		drawDailyChart(data.daily || []);
		var counts = extractActionCounts(data.by_action || []);
		drawDonutChart(counts.accept, counts.reject, counts.custom);
	}

	function updateKPIs(data) {
		var counts = extractActionCounts(data.by_action || []);
		var total = data.total || 0;
		var acceptRate = total > 0 ? (counts.accept / total * 100).toFixed(1) : '0';
		var rejectRate = total > 0 ? (counts.reject / total * 100).toFixed(1) : '0';
		var customRate = total > 0 ? (counts.custom / total * 100).toFixed(1) : '0';

		$('#kpi-total').text(total.toLocaleString());
		$('#kpi-accept').text(acceptRate + '%');
		$('#kpi-reject').text(rejectRate + '%');
		$('#kpi-custom').text(customRate + '%');
		$('#kpi-visitors').text((data.unique_visitors || 0).toLocaleString());

		$('#legend-accept').text(counts.accept);
		$('#legend-reject').text(counts.reject);
		$('#legend-custom').text(counts.custom);

		// Category bars
		var cats = data.category_accepts || {};
		var catMap = { analytics: '#3b82f6', marketing: '#ec4899', functional: '#10b981' };
		$('.wscm-bar-row').each(function () {
			var $val = $(this).find('.wscm-bar-value');
			var catKey = $val.data('cat');
			if (!catKey) return;
			var count = parseInt(cats[catKey], 10) || 0;
			var pct = total > 0 ? (count / total * 100).toFixed(1) : '0';
			$val.text(pct + '%');
			$(this).find('.wscm-bar-fill').css('width', pct + '%');
			$(this).find('.wscm-bar-count').text('(' + count.toLocaleString() + ')');
		});
	}

	// Initial chart render
	if (stats.daily) {
		renderCharts(stats);
	}

	// Date range picker
	$('#wscm-stats-range').on('change', function () {
		var days = $(this).val();
		$.get(wscm_admin.ajax_url, {
			action: 'wscm_get_stats',
			nonce: wscm_admin.nonce,
			days: days
		}).done(function (response) {
			if (response.success) {
				stats = response.data;
				updateKPIs(stats);
				renderCharts(stats);
			}
		});
	});

	// Purge logs
	$(document).on('click', '#wscm-purge-btn', function () {
		if (!confirm('Toestemmingslogs ouder dan 90 dagen verwijderen? Dit kan niet ongedaan worden gemaakt.')) return;
		var $btn = $(this);
		$btn.prop('disabled', true).text('Bezig met opschonen…');

		$.post(wscm_admin.ajax_url, {
			action: 'wscm_purge_logs',
			nonce: wscm_admin.nonce,
			older_than: 90
		}).done(function (response) {
			if (response.success) {
				var count = parseInt(response.data.deleted, 10) || 0;
				if (count > 0) {
					alert(count + ' logregels verwijderd.');
				} else {
					alert('Er zijn geen logregels ouder dan 90 dagen gevonden.');
				}
				$('#wscm-stats-range').trigger('change');
			} else {
				alert('Er is een fout opgetreden bij het opschonen.');
			}
			$btn.prop('disabled', false).text('Oude logs opschonen');
		}).fail(function () {
			alert('Er is een fout opgetreden. Probeer het opnieuw.');
			$btn.prop('disabled', false).text('Oude logs opschonen');
		});
	});

	// Redraw charts on window resize
	var resizeTimer;
	$(window).on('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			if (stats.daily) renderCharts(stats);
		}, 200);
	});

	// ==========================================
	// Copy privacy policy to clipboard
	// ==========================================
	$(document).on('click', '#wscm-copy-policy', function () {
		var $btn = $(this);
		var preview = document.getElementById('wscm-policy-preview');
		if (!preview) return;

		var html = preview.innerHTML;
		var text = preview.innerText;

		function onSuccess() {
			var original = $btn.html();
			$btn.addClass('copied').html('<span class="dashicons dashicons-yes"></span> Gekopieerd!');
			setTimeout(function () {
				$btn.removeClass('copied').html(original);
			}, 2000);
		}

		function copyFallback() {
			var range = document.createRange();
			range.selectNodeContents(preview);
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
			try {
				document.execCommand('copy');
				onSuccess();
			} catch (e) {
				alert('Kopiëren mislukt. Selecteer de tekst handmatig en druk op Ctrl+C.');
			}
			sel.removeAllRanges();
		}

		if (navigator.clipboard && typeof ClipboardItem !== 'undefined') {
			try {
				var htmlBlob = new Blob([html], { type: 'text/html' });
				var textBlob = new Blob([text], { type: 'text/plain' });
				var item = new ClipboardItem({
					'text/html': htmlBlob,
					'text/plain': textBlob
				});
				navigator.clipboard.write([item]).then(onSuccess).catch(function () {
					copyFallback();
				});
			} catch (e) {
				copyFallback();
			}
		} else {
			copyFallback();
		}
	});

	// ==========================================
	// Custom / Manual Scripts
	// ==========================================
	var customIdx = $('#wscm-custom-scripts .wscm-custom-row').length;

	$('#wscm-add-custom').on('click', function () {
		var idx = customIdx++;
		var row = '<div class="wscm-custom-row" data-index="' + idx + '">' +
			'<input type="text" name="custom_scripts[' + idx + '][name]" value="" placeholder="Naam (bijv. Google Maps)" class="wscm-custom-name">' +
			'<input type="text" name="custom_scripts[' + idx + '][pattern]" value="" placeholder="Patroon (bijv. maps.googleapis.com)" class="wscm-custom-pattern">' +
			'<select name="custom_scripts[' + idx + '][category]" class="wscm-custom-category">' +
				'<option value="analytics">Analytisch</option>' +
				'<option value="marketing">Marketing</option>' +
				'<option value="functional">Functioneel</option>' +
			'</select>' +
			'<label class="wscm-toggle wscm-custom-toggle">' +
				'<input type="checkbox" name="custom_scripts[' + idx + '][blocked]" value="1" checked>' +
				'<span class="wscm-toggle-slider"></span>' +
			'</label>' +
			'<button type="button" class="button wscm-remove-custom" title="Verwijderen">' +
				'<span class="dashicons dashicons-trash"></span>' +
			'</button>' +
		'</div>';
		$('#wscm-custom-scripts').append(row);
	});

	$(document).on('click', '.wscm-remove-custom', function () {
		$(this).closest('.wscm-custom-row').remove();
	});

	// Hide save button on tabs that don't need it (initial state)
	var activeTab = $('.wscm-tab.active').data('tab');
	if (activeTab === 'privacy' || activeTab === 'stats') {
		$('#wscm-settings-form .submit').hide();
	}

})(jQuery);
