/* global window, document, Blob, URL, TextEncoder, atob */
/**
 * Creates a self-contained, downloadable PDF receipt for payment add-ons.
 * Text is drawn by the browser before embedding it in the PDF, preserving
 * right-to-left languages without adding a third-party PDF dependency.
 */
(function (window, document) {
  'use strict';

  function roundedRect(ctx, x, y, width, height, radius) {
    var r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
  }

  function textLines(ctx, text, maxWidth) {
    var words = String(text || '\u2014').replace(/\s+/g, ' ').trim().split(' ');
    var lines = [];
    var line = '';
    words.forEach(function (word) {
      var candidate = line ? line + ' ' + word : word;
      if (ctx.measureText(candidate).width <= maxWidth) {
        line = candidate;
        return;
      }
      if (line) lines.push(line);
      line = '';
      while (ctx.measureText(word).width > maxWidth && word.length > 1) {
        var cut = word.length - 1;
        while (cut > 1 && ctx.measureText(word.slice(0, cut)).width > maxWidth) cut--;
        lines.push(word.slice(0, cut));
        word = word.slice(cut);
      }
      line = word;
    });
    if (line) lines.push(line);
    return lines.length ? lines : ['\u2014'];
  }

  function drawPage(options) {
    var width = 1240;
    var height = 1754;
    var margin = 70;
    var gap = 22;
    var top = 285;
    var footerSpace = 95;
    var canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    var ctx = canvas.getContext('2d');
    var isRtl = !!options.rtl;
    var fields = options.fields;
    var cardWidth = (width - margin * 2 - gap) / 2;

    function buildGrid(scale) {
      var rows = [];
      var totalHeight = 0;
      for (var i = 0; i < fields.length; i += 2) {
        var cards = [];
        var rowHeight = 0;
        for (var column = 0; column < 2 && i + column < fields.length; column++) {
          var field = fields[i + column];
          ctx.font = '500 ' + Math.round(29 * scale) + 'px system-ui, -apple-system, Segoe UI, sans-serif';
          var lines = textLines(ctx, String(field[1] || '\u2014'), cardWidth - 54 * scale);
          var cardHeight = Math.max(86 * scale, 47 * scale + lines.length * 37 * scale);
          cards.push({ label: String(field[0] || ''), lines: lines, column: column, height: cardHeight });
          rowHeight = Math.max(rowHeight, cardHeight);
        }
        rows.push({ cards: cards, height: rowHeight });
        totalHeight += rowHeight + gap * scale;
      }
      return { rows: rows, totalHeight: totalHeight };
    }

    ctx.direction = isRtl ? 'rtl' : 'ltr';
    ctx.textAlign = isRtl ? 'right' : 'left';
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, width, height);
    ctx.fillStyle = options.brandColor || '#2563eb';
    ctx.fillRect(0, 0, width, 240);
    ctx.fillStyle = '#ffffff';
    ctx.font = '600 38px system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.fillText('Easy Form Builder', isRtl ? width - margin : margin, 88);
    ctx.font = '700 64px system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.fillText(options.brand || 'Payment', isRtl ? width - margin : margin, 160);
    ctx.font = '400 30px system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.fillText(options.title, isRtl ? width - margin : margin, 208);

    var scale = 1;
    var grid = buildGrid(scale);
    while (grid.totalHeight > height - top - footerSpace && scale > 0.22) {
      scale -= 0.04;
      grid = buildGrid(scale);
    }

    var y = top;
    grid.rows.forEach(function (row) {
      row.cards.forEach(function (card) {
        var visualColumn = isRtl ? 1 - card.column : card.column;
        var x = margin + visualColumn * (cardWidth + gap);
        var textX = isRtl ? x + cardWidth - 24 * scale : x + 24 * scale;
        ctx.fillStyle = '#ffffff';
        roundedRect(ctx, x, y, cardWidth, row.height, 16 * scale);
        ctx.fill();
        ctx.fillStyle = '#64748b';
        ctx.font = '600 ' + Math.round(22 * scale) + 'px system-ui, -apple-system, Segoe UI, sans-serif';
        ctx.fillText(card.label, textX, y + 33 * scale);
        ctx.fillStyle = '#172033';
        ctx.font = '500 ' + Math.round(29 * scale) + 'px system-ui, -apple-system, Segoe UI, sans-serif';
        card.lines.forEach(function (line, lineIndex) {
          ctx.fillText(line, textX, y + 68 * scale + lineIndex * 35 * scale);
        });
      });
      y += row.height + gap * scale;
    });

    ctx.fillStyle = '#94a3b8';
    ctx.font = '400 22px system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.fillText(options.generatedAt || new Date().toLocaleString(), isRtl ? width - margin : margin, height - 48);
    return canvas;
  }

  function dataUrlBytes(dataUrl) {
    var raw = atob(dataUrl.split(',')[1]);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    return bytes;
  }

  function concatBytes(parts) {
    var length = parts.reduce(function (sum, part) { return sum + part.length; }, 0);
    var result = new Uint8Array(length);
    var offset = 0;
    parts.forEach(function (part) {
      result.set(part, offset);
      offset += part.length;
    });
    return result;
  }

  function makePdf(images) {
    var encoder = new TextEncoder();
    var ascii = function (value) { return encoder.encode(value); };
    var pageCount = images.length;
    var objectCount = 2 + pageCount * 3;
    var parts = [ascii('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n')];
    var offsets = new Array(objectCount + 1);
    var offset = parts[0].length;

    function object(number, chunks) {
      offsets[number] = offset;
      var all = [ascii(number + ' 0 obj\n')].concat(chunks).concat([ascii('\nendobj\n')]);
      all.forEach(function (chunk) {
        parts.push(chunk);
        offset += chunk.length;
      });
    }

    object(1, [ascii('<< /Type /Catalog /Pages 2 0 R >>')]);
    var kids = [];
    for (var i = 0; i < pageCount; i++) kids.push((3 + i * 3) + ' 0 R');
    object(2, [ascii('<< /Type /Pages /Kids [' + kids.join(' ') + '] /Count ' + pageCount + ' >>')]);

    images.forEach(function (image, i) {
      var pageObject = 3 + i * 3;
      var imageObject = pageObject + 1;
      var contentObject = pageObject + 2;
      var content = 'q\n595.28 0 0 841.89 0 0 cm\n/Im' + (i + 1) + ' Do\nQ\n';
      object(pageObject, [ascii('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /XObject << /Im' + (i + 1) + ' ' + imageObject + ' 0 R >> >> /Contents ' + contentObject + ' 0 R >>')]);
      object(imageObject, [ascii('<< /Type /XObject /Subtype /Image /Width 1240 /Height 1754 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + image.length + ' >>\nstream\n'), image, ascii('\nendstream')]);
      object(contentObject, [ascii('<< /Length ' + content.length + ' >>\nstream\n' + content + 'endstream')]);
    });

    var xrefOffset = offset;
    var xref = 'xref\n0 ' + (objectCount + 1) + '\n0000000000 65535 f \n';
    for (var n = 1; n <= objectCount; n++) xref += String(offsets[n]).padStart(10, '0') + ' 00000 n \n';
    xref += 'trailer\n<< /Size ' + (objectCount + 1) + ' /Root 1 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF';
    parts.push(ascii(xref));
    return concatBytes(parts);
  }

  window.efbDownloadPaymentPdf = function (options) {
    if (!options || !Array.isArray(options.fields) || !options.fields.length) return false;
    var canvas = drawPage(options);
    var image = dataUrlBytes(canvas.toDataURL('image/jpeg', 0.92));
    var blob = new Blob([makePdf([image])], { type: 'application/pdf' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.href = url;
    link.download = options.filename || 'efb-payment.pdf';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    return true;
  };
})(window, document);
