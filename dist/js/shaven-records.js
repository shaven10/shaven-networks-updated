(function ($) {
  if (!$ || !$.fn || !$.fn.DataTable) {
    return;
  }
  var original = $.fn.DataTable;

  function shavenSubscriberGroups(settings, indexes) {
    var src = settings._shavenGroupSrc;
    var groups = [];
    var last = null;
    var i, name;
    for (i = 0; i < indexes.length; i++) {
      name = src(settings.aoData[indexes[i]]._aData);
      if (!groups.length || last !== name) {
        groups.push({ name: name, rowStart: i, rowEnd: i + 1 });
        last = name;
      } else {
        groups[groups.length - 1].rowEnd = i + 1;
      }
    }
    return groups;
  }

  function shavenOrderedGroupCount(settings, indexes) {
    var src = settings._shavenGroupSrc;
    var rows = [];
    var i, name;
    for (i = 0; i < indexes.length; i++) {
      name = src(settings.aoData[indexes[i]]._aData);
      rows.push({ name: name, key: String(name).toLowerCase(), pos: i });
    }
    rows.sort(function (a, b) {
      if (a.key < b.key) {
        return -1;
      }
      if (a.key > b.key) {
        return 1;
      }
      return a.pos - b.pos;
    });
    var count = 0;
    var last = null;
    for (i = 0; i < rows.length; i++) {
      if (last !== rows[i].name) {
        count++;
        last = rows[i].name;
      }
    }
    return count;
  }

  function shavenAlignSubscriberStart(start, len, count) {
    if (len === -1 || !len || count <= 0) {
      return 0;
    }
    if (start < 0 || isNaN(start)) {
      start = 0;
    }
    if (start >= count) {
      start = Math.floor((count - 1) / len) * len;
    }
    return start - (start % len);
  }

  function shavenInstallSubscriberCounters(settings) {
    if (settings._shavenCounters) {
      return;
    }
    settings._shavenCounters = true;
    settings.fnRecordsDisplay = function () {
      return this._shavenFilteredSubs == null ? 0 : this._shavenFilteredSubs;
    };
    settings.fnRecordsTotal = function () {
      return this._shavenTotalSubs == null ? 0 : this._shavenTotalSubs;
    };
    settings.fnDisplayEnd = function () {
      if (this._shavenRowPhase) {
        return this._shavenRowEnd || 0;
      }
      var len = this._iDisplayLength;
      var start = this._iDisplayStart;
      var records = this._shavenFilteredSubs || 0;
      if (!this.oFeatures.bPaginate || len === -1 || start + len > records) {
        return records;
      }
      return start + len;
    };
  }

  function shavenEnterSubscriberPhase(settings) {
    if (!settings || !settings._shavenRowPhase) {
      return;
    }
    settings._iDisplayStart = settings._shavenSavedStart || 0;
    settings._shavenRowPhase = false;
  }

  function shavenEnterSubscriberRowPhase(settings) {
    if (settings._shavenRowPhase) {
      return;
    }
    var groups = shavenSubscriberGroups(settings, settings.aiDisplay);
    var len = settings._iDisplayLength;
    var start = shavenAlignSubscriberStart(settings._iDisplayStart, len, groups.length);
    settings._shavenGroups = groups;
    settings._shavenFilteredSubs = groups.length;
    if (!settings.oPreviousSearch || !settings.oPreviousSearch.sSearch) {
      settings._shavenBaseSubs = groups.length;
    } else if (settings._shavenBaseSubs == null) {
      settings._shavenBaseSubs = shavenOrderedGroupCount(settings, settings.aiDisplayMaster);
    }
    settings._shavenTotalSubs = settings._shavenBaseSubs;
    settings._shavenSavedStart = start;
    if (!groups.length || len === -1) {
      settings._iDisplayStart = 0;
      settings._shavenRowEnd = groups.length ? settings.aiDisplay.length : 0;
    } else {
      var end = Math.min(start + len, groups.length);
      settings._iDisplayStart = groups[start].rowStart;
      settings._shavenRowEnd = groups[end - 1].rowEnd;
    }
    settings._shavenRowPhase = true;
  }

  function shavenWrapSubscriberPaging(settings) {
    var list = settings.aoDrawCallback;
    var i;
    for (i = 0; i < list.length; i++) {
      if (list[i].sName === 'pagination' && !list[i]._shavenWrapped) {
        list[i]._shavenWrapped = true;
        list[i].fn = (function (originalFn) {
          return function (s) {
            shavenEnterSubscriberPhase(s);
            var result = originalFn.apply(this, arguments);
            shavenEnterSubscriberRowPhase(s);
            return result;
          };
        })(list[i].fn);
      }
    }
  }

  function shavenSubscriberInfo(settings) {
    var filtered = settings._shavenFilteredSubs || 0;
    var all = settings._shavenTotalSubs || 0;
    var fmt = settings.fnFormatNumber || function (value) { return value; };
    var text;
    if (!filtered) {
      text = 'No subscribers to show';
      if (all) {
        text += ' (filtered from ' + fmt.call(settings, all) + ' subscribers)';
      }
      return text;
    }
    var len = settings._iDisplayLength;
    var start = settings._shavenRowPhase ? (settings._shavenSavedStart || 0) : (settings._iDisplayStart || 0);
    var end = len === -1 ? filtered : Math.min(start + len, filtered);
    text = 'Showing ' + fmt.call(settings, start + 1) + ' to ' + fmt.call(settings, end) + ' of ' + fmt.call(settings, filtered) + ' subscribers';
    if (filtered !== all) {
      text += ' (filtered from ' + fmt.call(settings, all) + ' subscribers)';
    }
    return text;
  }

  function shavenSubscriberPreDraw(settings, groupSrc) {
    settings._shavenGroupSrc = groupSrc;
    settings._shavenCountBy = 'subscriber';
    shavenInstallSubscriberCounters(settings);
    shavenWrapSubscriberPaging(settings);
    shavenEnterSubscriberRowPhase(settings);
  }

  $.fn.DataTable = function (options) {
    if (!this.length || arguments.length === 0 || this.hasClass('dataTable')) {
      return original.apply(this, arguments);
    }
    var $table = $(this);
    $table.addClass('shaven-records');
    var opts = $.extend(true, {}, options || {});
    opts.autoWidth = false;
    opts.lengthChange = true;
    opts.pageLength = opts.pageLength || 25;
    opts.lengthMenu = opts.lengthMenu || [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']];
    opts.dom = "<'row shaven-dt-top'<'col-sm-12 col-md-6 shaven-dt-actions'><'col-sm-12 col-md-6 shaven-dt-filter'f>>" +
      "<'row shaven-dt-length'<'col-sm-12'l>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row shaven-dt-bottom'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>";
    opts.language = $.extend({
      search: 'Find',
      lengthMenu: 'Show _MENU_ records',
      info: 'Showing _START_ to _END_ of _TOTAL_ records',
      infoEmpty: 'No records to show',
      infoFiltered: '(filtered from _MAX_ records)',
      zeroRecords: 'No matching records',
      paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
    }, opts.language || {});
    function headerName(node) {
      return $.trim($(node).text()).toLowerCase().replace(/\s+/g, ' ');
    }
    function plain(value) {
      return $('<div>').html(value == null ? '' : value).text().replace(/\s+/g, ' ').trim();
    }
    function money(value) {
      var num = parseFloat(plain(value).replace(/[^0-9.-]/g, ''));
      return isNaN(num) ? 0 : num;
    }
    function peso(amount) {
      var sign = amount < 0 ? '-' : '';
      return sign + '₱' + Math.abs(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    var nameIndex = -1;
    var dueIndex = -1;
    var amountIndex = -1;
    var subscriberPaging = false;
    $table.find('thead th').each(function (index) {
      var name = headerName(this);
      if (nameIndex < 0 && (name === 'subscriber name' || name === 'subscriber' || name === 'name')) {
        nameIndex = index;
      }
      if (name === 'due date') {
        dueIndex = index;
      }
      if (amountIndex < 0 && (name === 'amount' || name === 'bill' || name.indexOf('php') !== -1)) {
        amountIndex = index;
      }
    });
    var locked = $table.attr('data-install-order');
    var groupOff = String($table.attr('data-shaven-group') || '').toLowerCase() === 'off';
    if (locked !== undefined && locked !== '') {
      var column = parseInt(locked, 10);
      if (!isNaN(column)) {
        opts.order = [[column, 'asc']];
        opts.orderFixed = { pre: [[column, 'asc']] };
      }
    } else if (!groupOff && nameIndex >= 0 && dueIndex >= 0 && $.fn.dataTable.RowGroup) {
      var dueDir = String($table.attr('data-due-order') || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
      opts.order = [[nameIndex, 'asc'], [dueIndex, dueDir]];
      opts.orderFixed = { pre: [[nameIndex, 'asc']] };
      opts.columnDefs = opts.columnDefs || [];
      opts.columnDefs.push({
        targets: nameIndex,
        render: function (data, type) {
          if (type === 'sort' || type === 'type' || type === 'filter') {
            return plain(data).toLowerCase();
          }
          return data;
        }
      });
      var groupSrc = function (row) {
        return plain(row[nameIndex]) || 'Unnamed subscriber';
      };
      opts.rowGroup = {
        dataSrc: groupSrc,
        startRender: function (rows, group) {
          var count = rows.count();
          var meta = count + (count === 1 ? ' record' : ' records');
          if (amountIndex >= 0) {
            var total = 0;
            rows.data().each(function (row) {
              total += money(row[amountIndex]);
            });
            meta += ' · ' + peso(total);
          }
          return '<div class="shaven-bill-group"><span class="shaven-group-name">' +
            $('<div>').text(group).html() +
            '</span><span class="shaven-group-meta">' + meta + '</span></div>';
        }
      };
      if (String($table.attr('data-count-by') || '').toLowerCase() === 'subscriber') {
        subscriberPaging = true;
        opts.language.lengthMenu = 'Show _MENU_ subscribers';
        opts.language.info = 'Showing _START_ to _END_ of _TOTAL_ subscribers';
        opts.language.infoEmpty = 'No subscribers to show';
        opts.language.infoFiltered = '(filtered from _MAX_ subscribers)';
        opts.language.zeroRecords = 'No matching subscribers';
        var priorPreDraw = opts.preDrawCallback;
        opts.preDrawCallback = function (settings) {
          if (priorPreDraw && priorPreDraw.apply(this, arguments) === false) {
            return false;
          }
          shavenSubscriberPreDraw(settings, groupSrc);
        };
        opts.infoCallback = function (settings) {
          return shavenSubscriberInfo(settings);
        };
      }
    } else if (dueIndex >= 0) {
      var fallbackDir = String($table.attr('data-due-order') || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
      opts.order = [[dueIndex, fallbackDir]];
    }
    if (!opts.buttons && $table.attr('id') === 'example1' && $.fn.dataTable && $.fn.dataTable.Buttons) {
      opts.buttons = [
        { extend: 'copy', text: 'Copy', exportOptions: { columns: ':visible' } },
        { extend: 'csv', text: 'CSV', exportOptions: { columns: ':visible' } },
        { extend: 'excel', text: 'Excel', exportOptions: { columns: ':visible' } },
        { extend: 'pdf', text: 'PDF', orientation: 'landscape', pageSize: 'LETTER', exportOptions: { columns: ':visible' } },
        { extend: 'print', text: 'Print', exportOptions: { columns: ':visible' } },
        { extend: 'colvis', text: 'Columns' }
      ];
    }
    var api = original.call(this, opts);
    if (api && api.table) {
      $(api.table().container()).addClass('shaven-records-wrap');
      $(api.table().container()).find('.dataTables_filter input').attr('placeholder', 'Name, plan, date, or amount');
      if (opts.rowGroup && window.shavenRefreshGroups) {
        api.on('draw.shavenGroups', function () {
          window.shavenRefreshGroups(api.table().node());
        });
        window.shavenRefreshGroups(api.table().node());
      }
      if (subscriberPaging && api.settings) {
        api.on('draw.shavenSubscribers', function (event, settings) {
          shavenEnterSubscriberPhase(settings || api.settings()[0]);
        });
        shavenEnterSubscriberPhase(api.settings()[0]);
      }
    }
    return api;
  };
})(jQuery);
