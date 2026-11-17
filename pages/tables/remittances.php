<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (!shaven_role_is(1, 2, 3)) {
    shaven_redirect('/index.php?denied=1');
}

$userId = (int)($_SESSION['usr_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The remittance form expired. Submit it again.', 'err');
        shaven_redirect('/pages/tables/remittances.php');
    }
    $action = (string)($_POST['remit_action'] ?? '');
    if ($action === 'submit') {
        $result = shaven_remit_submit_batch($conn, $userId, $_POST['payment_ids'] ?? []);
        if ($result['ok']) {
            $result['error'] = 'Submitted batch ' . $result['ref'] . ' with ' . (int)$result['count'] . ' payment' . ((int)$result['count'] === 1 ? '' : 's') . ' totaling ₱' . number_format((float)$result['amount'], 2) . '. An admin confirms it before the cash is recorded as received.';
        }
    } elseif ($action === 'confirm') {
        $result = shaven_remit_confirm_batch($conn, $userId, (int)($_POST['batch_id'] ?? 0));
        if ($result['ok']) {
            $result['error'] = 'Confirmed ' . $result['ref'] . '. ' . (int)$result['count'] . ' payment' . ((int)$result['count'] === 1 ? '' : 's') . ' totaling ₱' . number_format((float)$result['amount'], 2) . ' recorded as received.';
        }
    } else {
        $result = ['ok' => false, 'error' => 'That remittance action is not available.'];
    }
    shaven_flash_set($result['error'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/tables/remittances.php');
}

$held = shaven_remit_held($conn, $userId);
$mine = shaven_remit_batches_for($conn, $userId);
$canConfirm = shaven_role_is(1, 2);
$pending = $canConfirm ? shaven_remit_pending($conn, $userId) : [];
$field = $canConfirm ? shaven_remit_field_holding($conn) : [];

function shaven_remit_rows_table(array $rows, $withPick) {
    ?>
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <?php if ($withPick): ?><th style="width:2.5rem;"></th><?php endif; ?>
            <th>Subscriber</th>
            <th>Billing period</th>
            <th>Due date</th>
            <th>Posted</th>
            <th>Receipt</th>
            <th class="text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $due = shaven_due_text($row, $row['s_doi']); ?>
          <tr>
            <?php if ($withPick): ?>
              <td><input class="remit-pick" type="checkbox" name="payment_ids[]" value="<?php echo (int)$row['id']; ?>"></td>
            <?php endif; ?>
            <td><?php echo h($row['s_name']); ?></td>
            <td><?php echo h(shaven_bill_period_label($row, $row['s_doi'])); ?></td>
            <td><?php echo $due !== '' ? '<span class="shaven-due-date">' . h($due) . '</span>' : '—'; ?></td>
            <td><?php echo h(shaven_date($row['payment_date'], 'M j, Y g:i A')); ?></td>
            <td><?php echo h($row['rf']); ?></td>
            <td class="text-right"><?php echo shaven_peso($row['payment_amount']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

function shaven_remit_batch_tools(array $batches) {
    foreach ($batches as $batch) {
        if (empty($batch['items'])) {
            continue;
        }
        ?>
        <div class="card-tools">
          <span class="shaven-group-tools">
            <button type="button" class="btn btn-sm btn-default" data-remit-batches="expand">Expand all</button>
            <button type="button" class="btn btn-sm btn-default" data-remit-batches="collapse">Collapse all</button>
          </span>
        </div>
        <?php
        return;
    }
}

function shaven_remit_batch_panel(array $batch, $withConfirm) {
    $count = (int)($batch['live_count'] ?? 0);
    $hasItems = !empty($batch['items']);
    $status = (string)($batch['status'] ?? '');
    ?>
    <div class="shaven-remit-batch border rounded mb-3<?php echo $hasItems ? ' shaven-group-collapsed' : ''; ?>">
      <div class="shaven-remit-batch-head">
        <?php if ($hasItems): ?>
        <button type="button" class="shaven-remit-batch-toggle" aria-expanded="false" title="Show payments">
        <?php else: ?>
        <div class="shaven-remit-batch-summary">
        <?php endif; ?>
          <span class="shaven-remit-batch-title">
            <strong class="<?php echo $hasItems ? 'shaven-group-name' : ''; ?>"><?php echo h($batch['ref']); ?></strong>
            <?php if ($status === 'confirmed'): ?>
              <span class="badge badge-success">Confirmed</span>
            <?php elseif ($status === 'pending' && !$withConfirm): ?>
              <span class="badge badge-warning">Waiting for an admin</span>
            <?php endif; ?>
          </span>
          <span class="shaven-remit-batch-meta">
            <?php if ($withConfirm): ?><?php echo h($batch['collector'] ?? ''); ?> submitted<?php else: ?>Submitted<?php endif; ?>
            <?php echo h(shaven_date($batch['submitted_at'], 'M j, Y g:i A')); ?>
            · <?php echo $count; ?> payment<?php echo $count === 1 ? '' : 's'; ?>
            · <?php echo shaven_peso($batch['live_amount'] ?? 0); ?>
            <?php if ($status === 'confirmed' && !empty($batch['confirmer'])): ?>
              · confirmed by <?php echo h($batch['confirmer']); ?>
              <?php echo h(shaven_date($batch['confirmed_at'] ?? '', 'M j, Y g:i A')); ?>
            <?php endif; ?>
          </span>
        <?php if ($hasItems): ?>
        </button>
        <?php else: ?>
        </div>
        <?php endif; ?>
        <?php if ($withConfirm): ?>
          <form method="post" class="mb-0" onsubmit="return confirm('Confirm this remittance batch? The cash is recorded as received.');">
            <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
            <input type="hidden" name="remit_action" value="confirm">
            <input type="hidden" name="batch_id" value="<?php echo (int)$batch['id']; ?>">
            <button class="btn btn-success" type="submit">Confirm remittance</button>
          </form>
        <?php endif; ?>
      </div>
      <?php if ($hasItems): ?>
      <div class="shaven-remit-batch-body">
        <?php shaven_remit_rows_table($batch['items'], false); ?>
      </div>
      <?php endif; ?>
    </div>
    <?php
}

shaven_page_open('Remittances', 'Remittances');
?>
<p class="text-muted">Collections stay on the account that posted them. That account submits them as one batch, and an admin confirms the batch before the cash is recorded as received.</p>

<?php if ($canConfirm): ?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Batches waiting for confirmation</h3>
    <?php shaven_remit_batch_tools($pending); ?>
  </div>
  <div class="card-body">
    <?php if (!$pending): ?>
      <p class="mb-0">No remittance batch is waiting for confirmation.</p>
    <?php endif; ?>
    <?php foreach ($pending as $batch): ?>
      <?php shaven_remit_batch_panel($batch, true); ?>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title">Technician collections not yet submitted</h3></div>
  <div class="card-body p-0">
    <?php if (!$field): ?>
      <p class="p-3 mb-0">No technician is holding collections.</p>
    <?php else: ?>
      <table class="table table-striped mb-0">
        <thead><tr><th>Technician</th><th>Payments</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($field as $row): ?>
          <tr>
            <td><?php echo h($row['sys_user_fullname']); ?></td>
            <td><?php echo (int)$row['c']; ?></td>
            <td class="text-right"><?php echo shaven_peso($row['amt']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Collections on your account</h3>
  </div>
  <div class="card-body">
    <p>You are holding <?php echo (int)$held['count']; ?> payment<?php echo (int)$held['count'] === 1 ? '' : 's'; ?> totaling <?php echo shaven_peso($held['amount']); ?>. Select the ones to turn in and submit them as one batch.</p>
    <?php if ((int)$held['count'] > count($held['rows'])): ?>
      <p>This list shows the oldest <?php echo count($held['rows']); ?>. Submit this batch, then the next payments appear.</p>
    <?php endif; ?>
    <?php if (!$held['rows']): ?>
      <p class="mb-0">You have no collections waiting to be submitted.</p>
    <?php else: ?>
      <form method="post" onsubmit="return confirm('Submit the selected collections as one remittance batch?');">
        <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
        <input type="hidden" name="remit_action" value="submit">
        <div class="mb-3">
          <label class="mb-0"><input type="checkbox" id="remit-all"> Select all in this list</label>
        </div>
        <?php shaven_remit_rows_table($held['rows'], true); ?>
        <button class="btn btn-primary mt-3" type="submit">Submit batch</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Your batches</h3>
    <?php shaven_remit_batch_tools($mine); ?>
  </div>
  <div class="card-body">
    <?php if (!$mine): ?>
      <p class="mb-0">You have not submitted a remittance batch.</p>
    <?php endif; ?>
    <?php foreach ($mine as $batch): ?>
      <?php shaven_remit_batch_panel($batch, false); ?>
    <?php endforeach; ?>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function setBatch(batch, open) {
    var toggle = batch.querySelector('.shaven-remit-batch-toggle');
    if (!toggle) {
      return;
    }
    batch.classList.toggle('shaven-group-collapsed', !open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.title = open ? 'Hide payments' : 'Show payments';
  }

  document.querySelectorAll('.shaven-remit-batch-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var batch = toggle.closest('.shaven-remit-batch');
      setBatch(batch, batch.classList.contains('shaven-group-collapsed'));
    });
  });

  document.querySelectorAll('[data-remit-batches]').forEach(function (button) {
    button.addEventListener('click', function () {
      var open = button.getAttribute('data-remit-batches') === 'expand';
      button.closest('.card').querySelectorAll('.shaven-remit-batch').forEach(function (batch) {
        setBatch(batch, open);
      });
    });
  });

  var all = document.getElementById('remit-all');
  if (!all) return;
  all.addEventListener('change', function () {
    document.querySelectorAll('.remit-pick').forEach(function (box) {
      box.checked = all.checked;
    });
  });
});
</script>
<?php
shaven_page_close();
