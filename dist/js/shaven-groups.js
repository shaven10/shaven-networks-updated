(function ($) {
  if (!$ || window.shavenGroupsReady) {
    return;
  }
  window.shavenGroupsReady = true;

  function isGroup($row) {
    return $row.hasClass('dtrg-group') || $row.hasClass('shaven-bill-group');
  }

  function groupName($row) {
    return $.trim($row.find('.shaven-group-name').first().text());
  }

  function setMembers($group, open) {
    var $row = $group.next();
    while ($row.length && !isGroup($row)) {
      $row.toggleClass('shaven-group-member-hidden', !open);
      $row = $row.next();
    }
  }

  function groupIsOpen($table, name) {
    var overrides = $table.data('shavenGroupOverrides') || {};
    if (Object.prototype.hasOwnProperty.call(overrides, name)) {
      return !!overrides[name];
    }
    return $table.data('shavenGroupMode') === 'all';
  }

  function paintGroup($group, isOpen) {
    $group.toggleClass('shaven-group-collapsed', !isOpen);
    $group.attr({
      role: 'button',
      tabindex: '0',
      'aria-expanded': isOpen ? 'true' : 'false',
      title: isOpen ? 'Hide records' : 'Show records'
    });
    setMembers($group, isOpen);
  }

  function ensureToolbar($table) {
    if (!$table.find('tr.dtrg-group, tr.shaven-bill-group').length) {
      return;
    }
    var $card = $table.closest('.card');
    var $scope = $card.length ? $card : $table.parent();
    if ($scope.find('.shaven-group-tools').length) {
      return;
    }
    var $tools = $('<span class="shaven-group-tools">' +
      '<button type="button" class="btn btn-sm btn-default" data-shaven-groups="expand">Expand all</button>' +
      '<button type="button" class="btn btn-sm btn-default" data-shaven-groups="collapse">Collapse all</button>' +
      '</span>');
    var $header = $card.children('.card-header');
    if ($header.length) {
      var $slot = $header.children('.card-tools');
      if (!$slot.length) {
        $slot = $('<div class="card-tools"></div>').appendTo($header);
      }
      $slot.append($tools);
      return;
    }
    $table.before($tools);
  }

  window.shavenRefreshGroups = function (table) {
    var $table = $(table);
    ensureToolbar($table);
    $table.find('tbody').children('tr').each(function () {
      var $row = $(this);
      if (!isGroup($row)) {
        return;
      }
      paintGroup($row, groupIsOpen($table, groupName($row)));
    });
  };

  window.shavenSetAllGroups = function (table, open) {
    var $table = $(table);
    $table.data('shavenGroupMode', open ? 'all' : 'none');
    $table.removeData('shavenGroupOverrides');
    window.shavenRefreshGroups(table);
  };

  $(document).on('click.shavenGroups', '.shaven-group-tools [data-shaven-groups]', function (event) {
    event.preventDefault();
    var open = $(this).attr('data-shaven-groups') === 'expand';
    var $card = $(this).closest('.card');
    var table = $card.find('table').filter(function () {
      return $(this).find('tr.dtrg-group, tr.shaven-bill-group').length;
    }).get(0);
    if (table) {
      window.shavenSetAllGroups(table, open);
    }
  });

  $(document).on('click.shavenGroups', 'tr.dtrg-group, tr.shaven-bill-group', function (event) {
    event.stopImmediatePropagation();
    var $group = $(this);
    var $table = $group.closest('table');
    var overrides = $.extend({}, $table.data('shavenGroupOverrides') || {});
    var isOpen = $group.hasClass('shaven-group-collapsed');
    overrides[groupName($group)] = isOpen;
    $table.data('shavenGroupOverrides', overrides);
    paintGroup($group, isOpen);
  });

  $(document).on('keydown', 'tr.dtrg-group, tr.shaven-bill-group', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      $(this).trigger('click');
    }
  });

  $(function () {
    $('table').each(function () {
      if ($(this).find('tr.shaven-bill-group').length) {
        window.shavenRefreshGroups(this);
      }
    });
  });
})(window.jQuery);
