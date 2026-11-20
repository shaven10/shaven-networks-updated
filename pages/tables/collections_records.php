<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);
if (!shaven_role_is(1, 2, 3)) {
    shaven_redirect('/index.php?denied=1');
}

$views = shaven_collection_record_views(shaven_role());
$view = (string)($_GET['view'] ?? '');
if (!isset($views[$view])) {
    $view = shaven_role() === 3 ? 'unpaid' : 'paid';
}
$monthValue = $view === 'monthly' ? (string)($_GET['month'] ?? '') : '';
$fromValue = $view === 'posted' ? (string)($_GET['from'] ?? '') : '';
$toValue = $view === 'posted' ? (string)($_GET['to'] ?? '') : '';
$report = shaven_collection_record_query($conn, $view, $monthValue, $fromValue, $toValue);
$rows = $report['rows'];
$total = 0.0;
foreach ($rows as $row) {
    $total += (float)$row['payment_amount'];
}
$GLOBALS['shaven_needs_records'] = true;
shaven_page_open('Collection records', 'Collection records');
?>
<p><?php echo h($report['label']); ?>. Choose a record set to retrieve those bills.</p>
<form method="get" class="card" action="<?php echo h(shaven_url('pages/tables/collections_records.php')); ?>">
  <div class="card-body">
    <div class="form-row align-items-end">
      <div class="form-group col-md-4 mb-md-0">
        <label for="collection-view">Records</label>
        <select class="form-control" id="collection-view" name="view">
          <?php foreach ($views as $key => $name): ?>
            <option value="<?php echo h($key); ?>" <?php echo $view === $key ? 'selected' : ''; ?>><?php echo h($name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-3 mb-md-0" id="collection-month-wrap" <?php echo $view === 'monthly' ? '' : 'hidden'; ?>>
        <label for="collection-month">Month</label>
        <input class="form-control" id="collection-month" type="month" name="month" value="<?php echo h($monthValue); ?>" <?php echo $view === 'monthly' ? '' : 'disabled'; ?>>
      </div>
      <div class="form-group col-md-3 mb-md-0" id="collection-from-wrap" <?php echo $view === 'posted' ? '' : 'hidden'; ?>>
        <label for="collection-from">Posted from</label>
        <input class="form-control" id="collection-from" type="date" name="from" value="<?php echo h($fromValue); ?>" <?php echo $view === 'posted' ? '' : 'disabled'; ?>>
      </div>
      <div class="form-group col-md-3 mb-md-0" id="collection-to-wrap" <?php echo $view === 'posted' ? '' : 'hidden'; ?>>
        <label for="collection-to">Posted through</label>
        <input class="form-control" id="collection-to" type="date" name="to" value="<?php echo h($toValue); ?>" <?php echo $view === 'posted' ? '' : 'disabled'; ?>>
      </div>
      <div class="form-group col-md-2 mb-md-0">
        <button class="btn btn-primary" type="submit">Show records</button>
      </div>
    </div>
  </div>
</form>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?php echo h($views[$view]); ?></h3>
    <div class="card-tools"><span class="badge badge-warning"><?php echo count($rows); ?> bills · <?php echo shaven_peso($total); ?></span></div>
  </div>
  <div class="card-body">
    <table id="example1" class="table table-bordered table-striped" data-count-by="subscriber" data-due-order="asc">
      <thead>
        <tr>
          <th>Subscriber</th>
          <th>Plan</th>
          <th>Billing month</th>
          <th>Billing period</th>
          <th>Due date</th>
          <th>Amount</th>
          <th>Payment status</th>
          <th>Posted</th>
          <th>Posted by</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php $periodMeta = shaven_bill_period($row, $row['s_doi'] ?? ''); ?>
        <tr>
          <td><?php echo h($row['s_name']); ?></td>
          <td><?php echo h(($row['p_name'] ?: 'No plan') . ' · ' . shaven_speed_label($row['p_speed_kb'] ?? 0)); ?></td>
          <?php echo shaven_billing_month_cell($row); ?>
          <td class="shaven-period" data-order="<?php echo h(($periodMeta && !empty($periodMeta['start'])) ? $periodMeta['start'] : '9999-12-31'); ?>"><?php echo h(shaven_bill_period_label($row, $row['s_doi'] ?? '')); ?></td>
          <?php echo shaven_due_cell($row, $row['s_doi'] ?? ''); ?>
          <td><?php echo shaven_peso($row['payment_amount']); ?></td>
          <td><?php echo shaven_payment_status_badge($row['stat'] ?? 0); ?></td>
          <td><?php echo ((int)($row['stat'] ?? 0) === 1 && !empty($row['payment_date'])) ? h(shaven_date($row['payment_date'], 'M j, Y g:i A')) : '—'; ?></td>
          <td><?php echo trim((string)($row['posted_name'] ?? '')) !== '' ? h($row['posted_name']) : '—'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var view = document.getElementById('collection-view');
  var month = document.getElementById('collection-month-wrap');
  var input = document.getElementById('collection-month');
  var fromWrap = document.getElementById('collection-from-wrap');
  var toWrap = document.getElementById('collection-to-wrap');
  var fromInput = document.getElementById('collection-from');
  var toInput = document.getElementById('collection-to');
  if (!view || !month) return;
  function sync() {
    var monthly = view.value === 'monthly';
    var posted = view.value === 'posted';
    month.hidden = !monthly;
    if (input) input.disabled = !monthly;
    if (fromWrap) fromWrap.hidden = !posted;
    if (toWrap) toWrap.hidden = !posted;
    if (fromInput) fromInput.disabled = !posted;
    if (toInput) toInput.disabled = !posted;
  }
  view.addEventListener('change', sync);
  sync();
});
</script>
<?php
shaven_page_close();
