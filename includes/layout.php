<?php
function shaven_page_open($title, $heading) {
    $home = shaven_url('index.php');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($title); ?> | SHAVEN Networks</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="<?php echo h(shaven_url('plugins/fontawesome-free/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo h(shaven_url('dist/css/adminlte.min.css')); ?>">
  <?php shaven_theme_assets(); ?>
  <?php if (!empty($GLOBALS['shaven_needs_records'])): ?>
  <link rel="stylesheet" href="<?php echo h(shaven_url('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo h(shaven_url('plugins/datatables-responsive/css/responsive.bootstrap4.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo h(shaven_url('plugins/datatables-buttons/css/buttons.bootstrap4.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo h(shaven_url('dist/css/shaven-records.css')); ?>">
  <?php endif; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <?php require __DIR__ . '/topnav.php'; ?>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?php echo h($home); ?>" class="brand-link">
      <img src="<?php echo h(shaven_url('dist/img/AdminLTELogo.png')); ?>" alt="" class="brand-image img-circle elevation-3" style="opacity:.8">
      <span class="brand-text font-weight-light">SHAVEN Networks</span>
    </a>
    <?php require __DIR__ . '/sidebar.php'; ?>
  </aside>
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1 class="m-0"><?php echo h($heading); ?></h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo h($home); ?>">Home</a></li>
              <li class="breadcrumb-item active"><?php echo h(shaven_role_name()); ?></li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <section class="content"><div class="container-fluid">
    <?php
    if (isset($_GET['denied'])) {
        echo '<div class="alert alert-warning">That page is reserved for another role. Your menu shows the pages for ' . h(shaven_role_name()) . '.</div>';
    }
    shaven_flash_html();
}

function shaven_page_close() {
    $needsChart = !empty($GLOBALS['shaven_needs_chart']);
    ?>
    </div></section>
  </div>
  <footer class="main-footer">
    <strong>SHAVEN Networks</strong>
    <div class="float-right d-none d-sm-inline-block">Subscriber billing</div>
  </footer>
</div>
<script src="<?php echo h(shaven_url('plugins/jquery/jquery.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('dist/js/shaven-groups.js')); ?>?v=<?php echo (int)@filemtime(__DIR__ . '/../dist/js/shaven-groups.js'); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<?php if (!empty($GLOBALS['shaven_needs_records'])): ?>
<script src="<?php echo h(shaven_url('plugins/datatables/jquery.dataTables.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-rowgroup/js/dataTables.rowGroup.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('dist/js/shaven-records.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-responsive/js/dataTables.responsive.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-responsive/js/responsive.bootstrap4.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-buttons/js/dataTables.buttons.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-buttons/js/buttons.bootstrap4.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/jszip/jszip.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/pdfmake/pdfmake.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/pdfmake/vfs_fonts.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-buttons/js/buttons.html5.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-buttons/js/buttons.print.min.js')); ?>"></script>
<script src="<?php echo h(shaven_url('plugins/datatables-buttons/js/buttons.colVis.min.js')); ?>"></script>
<script>
$(function () {
  var table = document.getElementById('example1');
  if (table && $.fn.DataTable && !$(table).hasClass('dataTable')) {
    $(table).DataTable({});
  }
});
</script>
<?php endif; ?>
<?php if ($needsChart): ?>
<script src="<?php echo h(shaven_url('plugins/chart.js/Chart.min.js')); ?>"></script>
<?php endif; ?>
<script src="<?php echo h(shaven_url('dist/js/adminlte.min.js')); ?>"></script>
<script>
(function () {
  var root = document.getElementById('shaven-notify');
  if (!root || !window.fetch) return;
  var url = root.getAttribute('data-feed');
  var menu = document.getElementById('shaven-notify-menu');
  var badge = document.getElementById('shaven-notify-count');
  var toggle = document.getElementById('shaven-notify-toggle');
  function paint(data) {
    if (!data) return;
    var n = parseInt(data.unread, 10) || 0;
    if (badge) {
      badge.textContent = n > 99 ? '99+' : String(n);
      badge.classList.toggle('d-none', n < 1);
    }
    if (toggle) {
      toggle.setAttribute('aria-label', n > 0 ? ('Notifications, ' + n + ' unread') : 'Notifications');
    }
    if (menu && typeof data.html === 'string' && !menu.classList.contains('show')) {
      menu.innerHTML = data.html;
    }
  }
  function pull() {
    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(paint)
      .catch(function () {});
  }
  setInterval(pull, 30000);
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var holder = document.getElementById('dash-data');
  if (!holder || typeof Chart === 'undefined') return;
  var data = JSON.parse(holder.textContent);
  function themeColor(name, fallback, alpha) {
    var probe = document.createElement('span');
    probe.style.display = 'none';
    probe.style.color = 'var(' + name + ', ' + fallback + ')';
    document.body.appendChild(probe);
    var value = getComputedStyle(probe).color || fallback;
    probe.remove();
    var pixel = document.createElement('canvas').getContext('2d');
    pixel.fillStyle = fallback;
    pixel.fillStyle = value;
    pixel.fillRect(0, 0, 1, 1);
    var d = pixel.getImageData(0, 0, 1, 1).data;
    return 'rgba(' + d[0] + ',' + d[1] + ',' + d[2] + ',' + (alpha || 1) + ')';
  }
  var accent = themeColor('--shaven-teal', '#1f7a8c');
  var accentFill = themeColor('--shaven-teal', '#1f7a8c', 0.14);
  var colors = [
    accent,
    '#1f7a4d',
    themeColor('--shaven-gold', '#c4a15a'),
    '#a33d45',
    themeColor('--shaven-navy-soft', '#16465c'),
    themeColor('--shaven-navy', '#0e2a3a'),
    themeColor('--shaven-teal-dark', '#176274')
  ];
  function moneyChart(id, labels, datasets) {
    var canvas = document.getElementById(id);
    if (!canvas) return;
    new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          yAxes: [{ ticks: { beginAtZero: true } }],
          xAxes: [{ gridLines: { display: false } }]
        }
      }
    });
  }
  moneyChart('yearChart', data.months, [
    { label: 'Paid', data: data.paid, borderColor: '#1f7a4d', backgroundColor: 'rgba(31,122,77,.15)', fill: true, lineTension: 0.2 },
    { label: 'Unpaid', data: data.unpaid, borderColor: '#a33d45', backgroundColor: 'rgba(163,61,69,.10)', fill: true, lineTension: 0.2 }
  ]);
  moneyChart('dayChart', data.days, [
    { label: 'Collected', data: data.dayAmounts, borderColor: accent, backgroundColor: accentFill, fill: true, lineTension: 0.2 }
  ]);
  function doughnut(id, labels, counts) {
    var canvas = document.getElementById(id);
    if (!canvas || !labels.length) return;
    new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: { labels: labels, datasets: [{ data: counts, backgroundColor: colors }] },
      options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
    });
  }
  doughnut('planChart', data.planLabels, data.planCounts);
  doughnut('mediumChart', data.mediumLabels, data.mediumCounts);
});
</script>
</body>
</html>
    <?php
}
