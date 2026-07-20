/**
 * Easy Form Builder — Dashboard Widget JS
 * Lightweight canvas chart + AJAX data loading — no external chart library required.
 */
(function ($) {
  'use strict';

  if (typeof efb_dw === 'undefined') return;

  var t   = efb_dw.text || {};
  var currentPeriod = 'week';
  var chartData = null;
  var statsRequestId = 0;

  /* ── Translation helpers ───────────────────────────────────── */

  // Replace first %s in a translation string with a value (like PHP's sprintf for one arg)
  function fmt(str, val) {
    return (str || '').replace('%s', val || '');
  }

  var periodLabels = {
    day:   t.dayly   || 'Daily',
    week:  t.weekly  || 'Weekly'
  };

  /* ── Init ─────────────────────────────────────────────────── */
  $(function () {
    setTabLabels();
    bindTabs();
    bindEmailFailCard();
    bindErrorsClose();
    loadStats('week');
  });

  function setTabLabels() {
    $('.efb-dw-tab').each(function () {
      var p = $(this).data('period');
      $(this).text(periodLabels[p] || p);
    });
    // Card labels
    var pageWord = t.page || 'Page';
    var emailWord = t.email || 'Email';
    var errorWord = t.error || 'Error';
    $('#efb-dw-visits-label').text(fmt(t.dwVisits, pageWord) || pageWord + ' views');
    $('#efb-dw-submissions-label').text(t.dwSubmissions || 'Submissions');
    $('#efb-dw-email-ok-label').text(fmt(t.dwEmailsSent, emailWord) || 'Emails sent');
    $('#efb-dw-email-fail-label').text(fmt(t.dwEmailFailures, errorWord) || errorWord + ' Log');
  }

  function bindTabs() {
    $(document).on('click', '.efb-dw-tab', function () {
      $('.efb-dw-tab').removeClass('active');
      $(this).addClass('active');
      var p = $(this).data('period');
      currentPeriod = p;
      loadStats(p);
    });
  }

  function bindEmailFailCard() {
    $(document).on('click', '#efb-dw-email-fail-card', function () {
      var panel = $('#efb-dw-email-errors-panel');
      if (panel.is(':visible')) {
        panel.slideUp(200);
        return;
      }
      loadEmailErrors(currentPeriod);
    });
  }

  function bindErrorsClose() {
    $(document).on('click', '#efb-dw-errors-close', function () {
      $('#efb-dw-email-errors-panel').slideUp(200);
    });
  }

  /* ── Data loading ────────────────────────────────────────── */
  function loadStats(period) {
    var requestId = ++statsRequestId;
    showLoading(true);
    $.post(efb_dw.ajax_url, {
      action: 'efb_dashboard_stats',
      nonce:  efb_dw.nonce,
      period: period
    }, function (res) {
      if (requestId !== statsRequestId) return;
      showLoading(false);
      if (!res || !res.success) return;
      var d = res.data;
      animateNumber('#efb-dw-visits', d.visits);
      animateNumber('#efb-dw-submissions', d.submissions);
      animateNumber('#efb-dw-email-ok', d.email_ok);
      animateNumber('#efb-dw-email-fail', d.email_fail);
      chartData = d.chart;
      drawChart();
    }).fail(function () {
      if (requestId !== statsRequestId) return;
      showLoading(false);
    });
  }

  function loadEmailErrors(period) {
    var list = $('#efb-dw-errors-list');
    list.html('<div class="efb-dw-errors-empty"><span class="spinner is-active" style="float:none;"></span></div>');
    $('#efb-dw-email-errors-panel').slideDown(200);
    $('#efb-dw-errors-title').text(fmt(t.dwEmailErrors, t.email || 'Email') || (t.email || 'Email') + ' Error Log');

    $.post(efb_dw.ajax_url, {
      action: 'efb_dashboard_email_errors',
      nonce:  efb_dw.nonce,
      period: period
    }, function (res) {
      if (!res || !res.success || !res.data.errors.length) {
        list.html('<div class="efb-dw-errors-empty">' + (t.dwNoData || 'No data available for this period') + '</div>');
        return;
      }
      var html = '<table><thead><tr>' +
        '<th>' + (t.ddate || 'Date') + '</th>' +
        '<th>' + (t.dwRecipient || 'Recipient') + '</th>' +
        '<th>' + (t.subject || 'Subject') + '</th>' +
        '<th>' + (fmt(t.dwErrorDetail, t.error || 'Error') || (t.error || 'Error') + ' Details') + '</th>' +
        '</tr></thead><tbody>';
      res.data.errors.forEach(function (e) {
        html += '<tr>';
        html += '<td>' + escHtml(e.date) + '</td>';
        html += '<td>' + escHtml(e.to) + '</td>';
        html += '<td>' + escHtml(e.subject) + '</td>';
        html += '<td>' + escHtml(e.error || '—') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      list.html(html);
    }).fail(function () {
      list.html('<div class="efb-dw-errors-empty">' + (t.dwNoData || 'No data available for this period') + '</div>');
    });
  }

  /* ── Tiny Canvas Chart ──────────────────────────────────── */
  function drawChart() {
    var canvas = document.getElementById('efb-dw-chart');
    if (!canvas || !chartData) return;

    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.parentElement.getBoundingClientRect();
    var W = rect.width;
    var H = 200;
    var isRTL = parseInt(efb_dw.rtl, 10) === 1;

    canvas.width  = W * dpr;
    canvas.height = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    ctx.scale(dpr, dpr);
    ctx.direction = isRTL ? 'rtl' : 'ltr';

    var labels = chartData.labels || [];
    var visits = chartData.visits || [];
    var sends  = chartData.submissions || [];
    var n = labels.length;
    if (n === 0) return;

    var pad = {
      top: 20,
      right: isRTL ? 38 : 12,
      bottom: 34,
      left: isRTL ? 12 : 38
    };
    var cW = W - pad.left - pad.right;
    var cH = H - pad.top  - pad.bottom;

    // Find max
    var allVals = visits.concat(sends);
    var maxVal = Math.max.apply(null, allVals);
    if (maxVal === 0) maxVal = 1;
    var gridSteps = 4;
    var niceMax = computeYMax(maxVal, gridSteps);

    ctx.clearRect(0, 0, W, H);

    // Grid lines
    ctx.strokeStyle = '#e8e8e8';
    ctx.lineWidth = 1;
    ctx.fillStyle = '#999';
    ctx.font = '10px -apple-system, sans-serif';
    ctx.textAlign = isRTL ? 'left' : 'right';
    for (var g = 0; g <= gridSteps; g++) {
      var gy = pad.top + cH - (cH * g / gridSteps);
      ctx.beginPath();
      ctx.moveTo(pad.left, gy);
      ctx.lineTo(W - pad.right, gy);
      ctx.stroke();
      var gv = (niceMax / gridSteps) * g;
      ctx.fillText(gv, isRTL ? W - pad.right + 4 : pad.left - 4, gy + 3);
    }

    var barWidth = Math.max(4, (cW / n - 6) / 2);
    if (barWidth > 18) barWidth = 18;
    var gap = 2;
    var xLabelIndexes = getXAxisLabelIndexes(ctx, labels, cW);

    // Draw bars
    for (var i = 0; i < n; i++) {
      var cx = getXPosition(i, n, pad, cW, isRTL);

      // Visit bar
      var vh = (visits[i] / niceMax) * cH;
      if (visits[i] > 0 && vh < 2) vh = 2;
      drawRoundedBar(ctx, cx - barWidth - gap / 2, pad.top + cH - vh, barWidth, vh, 2, '#2271b1');

      // Send bar
      var sh = (sends[i] / niceMax) * cH;
      if (sends[i] > 0 && sh < 2) sh = 2;
      drawRoundedBar(ctx, cx + gap / 2, pad.top + cH - sh, barWidth, sh, 2, '#00a32a');
    }

    // Choose labels from the available space. The selected period's start and
    // end labels are always shown.
    drawXAxisLabels(ctx, labels, xLabelIndexes, pad, cW, H, isRTL);

    drawLegend(ctx, W, pad, isRTL);
  }

  function drawRoundedBar(ctx, x, y, w, h, r, color) {
    if (h <= 0) return;
    r = Math.min(r, h / 2, w / 2);
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h);
    ctx.lineTo(x, y + h);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
    ctx.fill();
  }

  /**
   * Return evenly distributed X-axis label indexes that fit in the chart.
   * This keeps dates legible in narrow dashboard columns and after resizing.
   */
  function getXAxisLabelIndexes(ctx, labels, chartWidth) {
    var n = labels.length;
    if (n < 2) return n ? [0] : [];

    ctx.save();
    ctx.font = '9px -apple-system, sans-serif';
    var widestLabel = 0;
    for (var i = 0; i < n; i++) {
      widestLabel = Math.max(widestLabel, ctx.measureText(labels[i]).width);
    }
    ctx.restore();

    // Leave a small visual gap so adjacent dates never touch.
    var maxLabels = Math.max(2, Math.floor(chartWidth / (widestLabel + 16)));
    if (n <= maxLabels) {
      return labels.map(function (_, index) { return index; });
    }

    var step = Math.ceil((n - 1) / (maxLabels - 1));
    var indexes = [];
    for (var j = 0; j < n; j += step) {
      indexes.push(j);
    }
    if (indexes[indexes.length - 1] !== n - 1) {
      indexes.push(n - 1);
    }
    return indexes;
  }

  function getXPosition(index, count, pad, chartWidth, isRTL) {
    var visualIndex = isRTL ? count - index - 1 : index;
    return pad.left + (visualIndex + 0.5) * (chartWidth / count);
  }

  function drawXAxisLabels(ctx, labels, indexes, pad, chartWidth, chartHeight, isRTL) {
    var lastIndex = labels.length - 1;
    ctx.fillStyle = '#888';
    ctx.font = '9px -apple-system, sans-serif';

    indexes.forEach(function (index) {
      var x = getXPosition(index, labels.length, pad, chartWidth, isRTL);

      // Align the edge labels inward, keeping them inside the visible canvas.
      if (index === 0) {
        ctx.textAlign = isRTL ? 'right' : 'left';
      } else if (index === lastIndex) {
        ctx.textAlign = isRTL ? 'left' : 'right';
      } else {
        ctx.textAlign = 'center';
      }

      ctx.fillText(labels[index], x, chartHeight - pad.bottom + 14);
    });
  }

  function drawLegend(ctx, chartWidth, pad, isRTL) {
    var legendY = 8;
    var itemGap = 20;
    var items = [
      { color: '#2271b1', label: t.form || 'Visit' },
      { color: '#00a32a', label: t.dwSubmissions || 'Submissions' }
    ];
    var x = isRTL ? chartWidth - pad.right : pad.left;

    ctx.font = '10px -apple-system, sans-serif';

    items.forEach(function (item) {
      var textWidth = ctx.measureText(item.label).width;
      ctx.fillStyle = item.color;

      if (isRTL) {
        ctx.fillRect(x - 10, legendY, 10, 10);
        ctx.fillStyle = '#555';
        ctx.textAlign = 'right';
        ctx.fillText(item.label, x - 14, legendY + 9);
        x -= textWidth + 14 + itemGap;
      } else {
        ctx.fillRect(x, legendY, 10, 10);
        ctx.fillStyle = '#555';
        ctx.textAlign = 'left';
        ctx.fillText(item.label, x + 14, legendY + 9);
        x += textWidth + 14 + itemGap;
      }
    });
  }

  /* ── Helpers ─────────────────────────────────────────────── */
  /**
   * Compute a Y-axis maximum that is always evenly divisible by `steps`,
   * so every gridline label is an exact integer aligned with its position.
   */
  function computeYMax(maxVal, steps) {
    if (maxVal <= 0) return steps;
    var roughTick = maxVal / steps;
    var tick;
    if (roughTick <= 1) {
      tick = 1;
    } else {
      var mag = Math.pow(10, Math.floor(Math.log10(roughTick)));
      var norm = roughTick / mag;
      if      (norm <= 2) tick = 2  * mag;
      else if (norm <= 5) tick = 5  * mag;
      else                tick = 10 * mag;
      tick = Math.max(1, tick);
    }
    return tick * steps;
  }

  function animateNumber(sel, target) {
    var $el = $(sel);
    var current = parseInt($el.text(), 10) || 0;
    if (current === target) { $el.text(target); return; }
    var diff = target - current;
    var steps = 15;
    var step = 0;
    var timer = setInterval(function () {
      step++;
      var val = Math.round(current + diff * (step / steps));
      $el.text(val);
      if (step >= steps) {
        $el.text(target);
        clearInterval(timer);
      }
    }, 20);
  }

  function showLoading(state) {
    $('#efb-dw-loading')[state ? 'show' : 'hide']();
  }

  function escHtml(str) {
    if (!str) return '';
    var el = document.createElement('span');
    el.textContent = str;
    return el.innerHTML;
  }

  // Redraw chart on window resize
  $(window).on('resize', function () {
    if (chartData) drawChart();
  });

})(jQuery);
