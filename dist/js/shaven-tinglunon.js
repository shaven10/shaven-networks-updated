(function () {
  var sheet = document.getElementById('tinglunon-sheet');
  var find = document.getElementById('tinglunon-find');
  var summary = document.getElementById('tinglunon-summary');
  var note = document.getElementById('tinglunon-export-note');
  if (!sheet || !summary) {
    return;
  }

  function peso(amount) {
    var sign = amount < 0 ? '-' : '';
    return sign + '₱' + Math.abs(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function visibleBills() {
    return Array.prototype.filter.call(sheet.querySelectorAll('[data-kind="bill"]'), function (row) {
      return !row.classList.contains('d-none');
    });
  }

  function refreshSummary() {
    var bills = visibleBills();
    var seen = {};
    var total = 0;
    bills.forEach(function (row) {
      seen[row.getAttribute('data-subscriber')] = true;
      total += parseFloat(row.getAttribute('data-amount') || '0') || 0;
    });
    var subscribers = Object.keys(seen).length;
    summary.textContent = subscribers + ' subscriber' + (subscribers === 1 ? '' : 's')
      + ' · ' + bills.length + ' pending bill' + (bills.length === 1 ? '' : 's')
      + ' · ' + peso(total);
    summary.setAttribute('data-subscribers', String(subscribers));
    summary.setAttribute('data-bills', String(bills.length));
    summary.setAttribute('data-total', peso(total));
  }

  function billsAfter(group) {
    var rows = [];
    var row = group.nextElementSibling;
    while (row && row.getAttribute('data-kind') !== 'group') {
      if (row.getAttribute('data-kind') === 'bill') {
        rows.push(row);
      }
      row = row.nextElementSibling;
    }
    return rows;
  }

  function setGroupOpen(group, open) {
    group.classList.toggle('shaven-group-collapsed', !open);
    group.setAttribute('aria-expanded', open ? 'true' : 'false');
    group.setAttribute('title', open ? 'Hide records' : 'Show records');
    billsAfter(group).forEach(function (row) {
      row.classList.toggle('shaven-group-member-hidden', !open);
    });
  }

  function applyFilter() {
    var query = find ? find.value.trim().toLowerCase() : '';
    var buckets = {};
    Array.prototype.forEach.call(sheet.querySelectorAll('.tinglunon-line'), function (row) {
      var id = row.getAttribute('data-subscriber');
      if (!buckets[id]) {
        buckets[id] = [];
      }
      buckets[id].push(row);
    });
    Object.keys(buckets).forEach(function (id) {
      var rows = buckets[id];
      var group = rows[0];
      var groupMatch = query === '' || (group.getAttribute('data-find') || '').indexOf(query) !== -1;
      var shown = 0;
      var shownTotal = 0;
      rows.slice(1).forEach(function (row) {
        var show = groupMatch || (row.getAttribute('data-find') || '').indexOf(query) !== -1;
        row.classList.toggle('d-none', !show);
        if (show) {
          shown += 1;
          shownTotal += parseFloat(row.getAttribute('data-amount') || '0') || 0;
        }
      });
      group.classList.toggle('d-none', shown === 0);
      group.setAttribute('data-amount-label', peso(shownTotal));
      var amountCell = group.querySelector('.tinglunon-money');
      if (amountCell) {
        amountCell.textContent = peso(shownTotal);
      }
      if (query === '') {
        setGroupOpen(group, false);
      } else if (shown > 0) {
        setGroupOpen(group, true);
      }
    });
    refreshSummary();
  }

  sheet.addEventListener('click', function (event) {
    var group = event.target.closest('tr.tinglunon-group');
    if (!group || !sheet.contains(group)) {
      return;
    }
    setGroupOpen(group, group.classList.contains('shaven-group-collapsed'));
  });

  sheet.addEventListener('keydown', function (event) {
    var group = event.target.closest('tr.tinglunon-group');
    if (!group || (event.key !== 'Enter' && event.key !== ' ')) {
      return;
    }
    event.preventDefault();
    setGroupOpen(group, group.classList.contains('shaven-group-collapsed'));
  });

  if (find) {
    find.addEventListener('input', applyFilter);
  }

  function fit(ctx, text, width) {
    text = String(text || '');
    if (ctx.measureText(text).width <= width) {
      return text;
    }
    var clipped = text;
    while (clipped.length > 1 && ctx.measureText(clipped + '…').width > width) {
      clipped = clipped.slice(0, -1);
    }
    return clipped + '…';
  }

  function linesForExport() {
    var lines = [];
    Array.prototype.forEach.call(sheet.querySelectorAll('.tinglunon-line'), function (row) {
      if (row.classList.contains('d-none')) {
        return;
      }
      if (row.getAttribute('data-kind') === 'group') {
        lines.push({
          kind: 'group',
          account: row.getAttribute('data-account') || '',
          name: row.getAttribute('data-name') || '',
          detail: row.getAttribute('data-detail') || '',
          payment: row.getAttribute('data-payment') || '',
          amount: row.getAttribute('data-amount-label') || ''
        });
        return;
      }
      lines.push({
        kind: 'bill',
        period: row.getAttribute('data-period') || '',
        due: row.getAttribute('data-due') || '',
        payment: row.getAttribute('data-payment') || '',
        amount: row.getAttribute('data-amount-label') || ''
      });
    });
    return lines;
  }

  function downloadCanvas(canvas, type, filename) {
    var link = document.createElement('a');
    link.href = canvas.toDataURL(type === 'jpeg' ? 'image/jpeg' : 'image/png', 0.92);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  function exportImages(type) {
    var lines = linesForExport();
    if (!lines.length) {
      if (note) {
        note.textContent = 'Nothing to export. Clear the filter or wait until a bill is pending.';
      }
      return;
    }
    var scale = 2;
    var width = 1480;
    var margin = 40;
    var maxHeight = 6400;
    var banner = 168;
    var columns = 36;
    var rowH = 44;
    var groupH = 52;
    var usable = maxHeight - banner - columns - 48;
    var pages = [];
    var current = [];
    var used = 0;
    lines.forEach(function (line) {
      var height = line.kind === 'group' ? groupH : rowH;
      if (current.length && used + height > usable) {
        pages.push(current);
        current = [];
        used = 0;
      }
      current.push(line);
      used += height;
    });
    if (current.length) {
      pages.push(current);
    }
    var stamp = sheet.getAttribute('data-stamp') || '';
    var asOf = sheet.getAttribute('data-asof') || '';
    var who = sheet.getAttribute('data-who') || '';
    var totalLabel = summary.getAttribute('data-total') || '';
    var billLabel = summary.getAttribute('data-bills') || '0';
    var subLabel = summary.getAttribute('data-subscribers') || '0';
    var mime = type === 'jpeg' ? 'jpeg' : 'png';
    var ext = mime === 'jpeg' ? 'jpg' : 'png';
    pages.forEach(function (pageLines, index) {
      var height = banner + columns + 48;
      pageLines.forEach(function (line) {
        height += line.kind === 'group' ? groupH : rowH;
      });
      var canvas = document.createElement('canvas');
      canvas.width = width * scale;
      canvas.height = height * scale;
      var ctx = canvas.getContext('2d');
      ctx.scale(scale, scale);
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, width, height);
      ctx.fillStyle = '#0e2a3a';
      ctx.fillRect(0, 0, width, banner);
      ctx.fillStyle = '#ffffff';
      ctx.font = '600 15px Segoe UI, Arial, sans-serif';
      ctx.fillText('SHAVEN NETWORKS', margin, 36);
      ctx.font = '700 36px Segoe UI, Arial, sans-serif';
      ctx.fillText('Tinglunon', margin, 80);
      ctx.font = '16px Segoe UI, Arial, sans-serif';
      ctx.fillStyle = '#d5e1e6';
      ctx.fillText('Due on or before ' + asOf + ' · ' + stamp, margin, 110);
      ctx.fillText(who + ' · page ' + (index + 1) + ' of ' + pages.length, margin, 136);
      ctx.fillStyle = '#c4a15a';
      ctx.font = '600 16px Segoe UI, Arial, sans-serif';
      var headline = subLabel + ' subscribers · ' + billLabel + ' bills · ' + totalLabel;
      ctx.fillText(headline, margin, 158);
      var y = banner;
      ctx.fillStyle = '#e7eef3';
      ctx.fillRect(0, y, width, columns);
      ctx.fillStyle = '#0e2a3a';
      ctx.font = '700 13px Segoe UI, Arial, sans-serif';
      ctx.fillText('SUBSCRIBER', margin, y + 23);
      ctx.fillText('PAYMENT STATUS', 480, y + 23);
      ctx.fillText('DUE DATE', 680, y + 23);
      ctx.textAlign = 'right';
      ctx.fillText('AMOUNT', width - margin, y + 23);
      ctx.textAlign = 'left';
      y += columns;
      pageLines.forEach(function (line) {
        if (line.kind === 'group') {
          ctx.fillStyle = '#f7f1e4';
          ctx.fillRect(0, y, width, groupH);
          ctx.fillStyle = '#0e2a3a';
          ctx.font = '700 15px Segoe UI, Arial, sans-serif';
          ctx.fillText(fit(ctx, line.name, 420), margin, y + 22);
          ctx.fillStyle = line.payment === 'Paid' ? '#1f7a4d' : '#a56b12';
          ctx.font = '700 14px Segoe UI, Arial, sans-serif';
          ctx.fillText(fit(ctx, line.payment, 140), 480, y + 28);
          ctx.fillStyle = '#5d6e78';
          ctx.font = '13px Segoe UI, Arial, sans-serif';
          var accountLine = (line.account || 'No account number') + (line.detail ? ' · ' + line.detail : '');
          ctx.fillText(fit(ctx, accountLine, 420), margin, y + 40);
          ctx.fillStyle = '#0e2a3a';
          ctx.font = '700 14px Segoe UI, Arial, sans-serif';
          ctx.textAlign = 'right';
          ctx.fillText(line.amount, width - margin, y + 28);
          ctx.textAlign = 'left';
          y += groupH;
          return;
        }
        ctx.fillStyle = line.payment === 'Paid' ? '#1f7a4d' : '#a56b12';
        ctx.font = '700 14px Segoe UI, Arial, sans-serif';
        ctx.fillText(fit(ctx, line.payment, 140), 480, y + 26);
        ctx.fillStyle = '#1b2b34';
        ctx.font = '700 14px Segoe UI, Arial, sans-serif';
        ctx.fillText(fit(ctx, line.due, 400), 680, y + 20);
        ctx.fillStyle = '#5d6e78';
        ctx.font = '12px Segoe UI, Arial, sans-serif';
        ctx.fillText(fit(ctx, line.period, 400), 680, y + 36);
        ctx.fillStyle = '#1b2b34';
        ctx.font = '14px Segoe UI, Arial, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(line.amount, width - margin, y + 26);
        ctx.textAlign = 'left';
        ctx.strokeStyle = '#d5e1e6';
        ctx.beginPath();
        ctx.moveTo(margin, y + rowH - 1);
        ctx.lineTo(width - margin, y + rowH - 1);
        ctx.stroke();
        y += rowH;
      });
      ctx.fillStyle = '#5d6e78';
      ctx.font = '12px Segoe UI, Arial, sans-serif';
      ctx.fillText('SHAVEN Networks · Tinglunon · due on or before ' + asOf, margin, height - 18);
      var filename = 'SHAVEN-Tinglunon' + (pages.length > 1 ? '-' + (index + 1) : '') + '.' + ext;
      downloadCanvas(canvas, mime, filename);
    });
    if (note) {
      note.textContent = pages.length === 1
        ? 'Saved 1 ' + ext.toUpperCase() + ' image.'
        : 'Saved ' + pages.length + ' ' + ext.toUpperCase() + ' images. A long list is split so every bill is included.';
    }
  }

  document.querySelectorAll('[data-tinglunon-export]').forEach(function (button) {
    button.addEventListener('click', function () {
      try {
        exportImages(button.getAttribute('data-tinglunon-export'));
      } catch (error) {
        if (note) {
          note.textContent = 'The image was not saved. Try the export again.';
        }
      }
    });
  });
})();
