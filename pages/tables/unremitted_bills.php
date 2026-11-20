<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (shaven_role() !== 1) {
    shaven_redirect('/index.php?denied=1');
}

$userId = (int)($_SESSION['usr_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The batch form expired. Submit it again.', 'err');
        shaven_redirect('/pages/tables/unremitted_bills.php');
    }
    $result = shaven_owner_change_batch_status(
        $conn,
        $userId,
        $_POST['bill_ids'] ?? [],
        $_POST['batch_status'] ?? ''
    );
    shaven_flash_set($result['message'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/tables/unremitted_bills.php');
}

$rows = shaven_owner_unremitted_bills($conn);
$total = 0.0;
foreach ($rows as $row) {
    $total += (float)$row['payment_amount'];
}
shaven_page_open('Unremitted bills', 'Unremitted bills');
?>
<p>These paid bills have not been remitted. Mark the bills to include, or mark all, then set their batch status. Not submitted takes a bill out of a batch. Pending groups the marked bills into one batch. Remitted records the cash as received.</p>
<form method="post" id="unremitted-form">
  <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Unremitted paid bills</h3>
      <div class="card-tools">
        <span class="badge badge-warning"><?php echo count($rows); ?> bills · <?php echo shaven_peso($total); ?></span>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$rows): ?>
        <p class="mb-0">Every paid bill is remitted.</p>
      <?php else: ?>
        <div class="form-row align-items-end">
          <div class="form-group col-md-4">
            <label for="batch-status">Batch status</label>
            <select class="form-control" id="batch-status" name="batch_status">
              <option value="held">Not submitted</option>
              <option value="pending">Pending</option>
              <option value="remitted">Remitted</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <button class="btn btn-primary" id="batch-update" type="submit" disabled>Update batch status</button>
          </div>
          <div class="form-group col-md-4">
            <p class="mb-2 text-muted" id="batch-marked">0 marked</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($rows): ?>
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th style="width:7.5rem;"><label class="mb-0"><input type="checkbox" id="bill-mark-all"> Mark all</label></th>
            <th>Subscriber</th>
            <th>Billing month</th>
            <th>Billing period</th>
            <th>Due date</th>
            <th>Posted</th>
            <th>Posted by</th>
            <th>Batch status</th>
            <th class="text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
            $due = shaven_due_text($row, $row['s_doi']);
            $pending = (string)($row['batch_status'] ?? '') === 'pending';
            $batchLabel = $pending ? 'Pending' : 'Not submitted';
          ?>
          <tr>
            <td><input class="bill-mark" type="checkbox" name="bill_ids[]" value="<?php echo (int)$row['id']; ?>" aria-label="Mark"></td>
            <td>
              <?php echo h($row['s_name']); ?>
              <?php if (!empty($row['account_no'])): ?>
                <div class="text-muted small"><?php echo h($row['account_no']); ?></div>
              <?php endif; ?>
            </td>
            <td><?php echo h(shaven_billing_month_label($row)); ?></td>
            <td><?php echo h(shaven_bill_period_label($row, $row['s_doi'])); ?></td>
            <td><?php echo $due !== '' ? '<span class="shaven-due-date">' . h($due) . '</span>' : '—'; ?></td>
            <td><?php echo !empty($row['payment_date']) ? h(shaven_date($row['payment_date'], 'M j, Y g:i A')) : '—'; ?></td>
            <td><?php echo h($row['poster'] ?: '—'); ?></td>
            <td>
              <span class="badge <?php echo $pending ? 'badge-warning' : 'badge-secondary'; ?>"><?php echo h($batchLabel); ?></span>
              <?php if ($pending && !empty($row['batch_ref'])): ?>
                <div class="small text-muted"><?php echo h($row['batch_ref']); ?></div>
              <?php endif; ?>
            </td>
            <td class="text-right"><?php echo shaven_peso($row['payment_amount']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('unremitted-form');
  var all = document.getElementById('bill-mark-all');
  var button = document.getElementById('batch-update');
  var marked = document.getElementById('batch-marked');
  if (!form || !all || !button || !marked) return;
  var boxes = Array.prototype.slice.call(form.querySelectorAll('.bill-mark'));
  function refresh() {
    var count = boxes.filter(function (box) { return box.checked; }).length;
    all.checked = count > 0 && count === boxes.length;
    button.disabled = count < 1;
    marked.textContent = count + ' marked';
  }
  all.addEventListener('change', function () {
    boxes.forEach(function (box) { box.checked = all.checked; });
    refresh();
  });
  boxes.forEach(function (box) {
    box.addEventListener('change', refresh);
  });
  form.addEventListener('submit', function (event) {
    var count = boxes.filter(function (box) { return box.checked; }).length;
    if (count < 1) {
      event.preventDefault();
      return;
    }
    var label = document.getElementById('batch-status').selectedOptions[0].text;
    if (!window.confirm('Update ' + count + ' marked bill' + (count === 1 ? '' : 's') + ' to ' + label + '?')) {
      event.preventDefault();
    }
  });
  refresh();
});
</script>
<?php
shaven_page_close();
