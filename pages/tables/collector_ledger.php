<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (!shaven_ledger_can_view()) {
    shaven_redirect('/index.php?denied=1');
}

$userId = (int)($_SESSION['usr_id'] ?? 0);
$canEdit = shaven_ledger_can_edit();
$collectors = $canEdit ? shaven_ledger_collectors($conn) : [];
$collectorId = $canEdit ? (int)($_REQUEST['collector'] ?? ($collectors[0]['id'] ?? 0)) : $userId;
$collector = shaven_ledger_collector($conn, $collectorId);
$self = '/pages/tables/collector_ledger.php' . ($canEdit && $collectorId > 0 ? '?collector=' . $collectorId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The ledger form expired. Submit it again.', 'err');
        shaven_redirect($self);
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $result = shaven_ledger_save($conn, $collectorId, $_POST, $userId, (int)($_POST['entry_id'] ?? 0));
    } elseif ($action === 'delete') {
        $result = shaven_ledger_delete($conn, $collectorId, (int)($_POST['entry_id'] ?? 0));
    } else {
        $result = ['ok' => false, 'error' => 'That ledger action is not available.'];
    }
    shaven_flash_set($result['error'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect($self);
}

$ledger = $collector ? shaven_ledger_entries($conn, $collectorId) : null;
$types = shaven_ledger_types();
$editId = $canEdit ? (int)($_GET['edit'] ?? 0) : 0;
$editRow = null;
if ($ledger && $editId > 0) {
    foreach ($ledger['rows'] as $row) {
        if ((int)$row['id'] === $editId) {
            $editRow = $row;
            break;
        }
    }
}
$collectorName = $collector ? (string)$collector['sys_user_fullname'] : '';

shaven_page_open('Collector Ledger', $canEdit ? 'Collector Ledger' : 'My Ledger');
?>
<p class="text-muted">
  <?php if ($canEdit): ?>
    Running balance between the owner and each collector. OUT is what the collector owes, like cash advances. IN is what the collector has earned or paid back, like monthly salary and labor. Only the owner can add, edit, or delete entries.
  <?php else: ?>
    Your running balance with the owner. OUT is what you owe, like cash advances. IN is what you earned or paid back, like monthly salary and labor. Only the owner can update this ledger.
  <?php endif; ?>
</p>

<?php if ($canEdit): ?>
  <form method="get" class="form-inline mb-3 shaven-no-print">
    <label class="mr-2" for="ledger-collector">Collector</label>
    <select class="form-control mr-2" id="ledger-collector" name="collector" onchange="this.form.submit()">
      <?php if (!$collectors): ?>
        <option value="">No collector accounts</option>
      <?php endif; ?>
      <?php foreach ($collectors as $option): ?>
        <option value="<?php echo (int)$option['id']; ?>" <?php echo (int)$option['id'] === $collectorId ? 'selected' : ''; ?>><?php echo h($option['sys_user_fullname']); ?></option>
      <?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>

<?php if (!$collector): ?>
  <div class="alert alert-warning">
    <?php echo $canEdit ? 'Choose a collector account. Collectors are accounts with the Technician role.' : 'This ledger is only for collector accounts.'; ?>
  </div>
<?php else: ?>
  <?php
    $balance = (float)$ledger['balance'];
    $balanceClass = $balance > 0 ? 'bg-danger' : ($balance < 0 ? 'bg-warning' : 'bg-success');
  ?>
  <div class="row">
    <div class="col-md-4">
      <div class="small-box <?php echo $balanceClass; ?>">
        <div class="inner">
          <h3><?php echo shaven_peso(abs($balance)); ?></h3>
          <p><?php echo h(shaven_ledger_balance_label($balance)); ?><?php echo $ledger['as_of'] ? ' · as of ' . h(shaven_date($ledger['as_of'], 'F j, Y')) : ''; ?></p>
        </div>
        <div class="icon"><i class="fas fa-balance-scale"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="small-box bg-info">
        <div class="inner">
          <h3><?php echo shaven_peso($ledger['out']); ?></h3>
          <p>Total OUT · owed by <?php echo h($collectorName); ?></p>
        </div>
        <div class="icon"><i class="fas fa-arrow-up"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="small-box bg-secondary">
        <div class="inner">
          <h3><?php echo shaven_peso($ledger['in']); ?></h3>
          <p>Total IN · salary, labor, and payments</p>
        </div>
        <div class="icon"><i class="fas fa-arrow-down"></i></div>
      </div>
    </div>
  </div>

  <?php if ($canEdit): ?>
    <div class="card <?php echo $editRow ? 'card-warning' : 'card-primary'; ?> shaven-no-print">
      <div class="card-header"><h3 class="card-title"><?php echo $editRow ? 'Edit entry' : 'Add entry'; ?> · <?php echo h($collectorName); ?></h3></div>
      <form method="post" action="<?php echo h(shaven_url(ltrim($self, '/'))); ?>">
        <div class="card-body">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="collector" value="<?php echo (int)$collectorId; ?>">
          <input type="hidden" name="entry_id" value="<?php echo $editRow ? (int)$editRow['id'] : 0; ?>">
          <div class="form-row">
            <div class="form-group col-md-2">
              <label for="ledger-date">Date</label>
              <input class="form-control" id="ledger-date" type="date" name="entry_date" required value="<?php echo h($editRow ? $editRow['entry_date'] : date('Y-m-d')); ?>">
            </div>
            <div class="form-group col-md-3">
              <label for="ledger-type">Type</label>
              <select class="form-control" id="ledger-type" name="entry_type" required>
                <?php foreach (['out' => 'OUT · collector owes', 'in' => 'IN · collector earned or paid'] as $side => $sideLabel): ?>
                  <optgroup label="<?php echo h($sideLabel); ?>">
                    <?php foreach ($types as $key => $type): ?>
                      <?php if ($type['side'] !== $side) { continue; } ?>
                      <option value="<?php echo h($key); ?>" <?php echo ($editRow ? $editRow['entry_type'] : 'advance') === $key ? 'selected' : ''; ?>><?php echo h($type['label']); ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-5">
              <label for="ledger-description">Description</label>
              <input class="form-control" id="ledger-description" name="description" maxlength="255" placeholder="Cash advance (Butser)" value="<?php echo h($editRow ? $editRow['description'] : ''); ?>">
            </div>
            <div class="form-group col-md-2">
              <label for="ledger-amount">Amount</label>
              <input class="form-control" id="ledger-amount" name="amount" type="number" step="0.01" min="0.01" required value="<?php echo $editRow ? h(number_format((float)$editRow['amount'], 2, '.', '')) : ''; ?>">
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button class="btn <?php echo $editRow ? 'btn-warning' : 'btn-primary'; ?>" type="submit"><?php echo $editRow ? 'Save changes' : 'Add entry'; ?></button>
          <?php if ($editRow): ?>
            <a class="btn btn-default" href="<?php echo h(shaven_url(ltrim($self, '/'))); ?>">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title mb-0">Ledger · <?php echo h($collectorName); ?></h3>
      <button class="btn btn-default btn-sm ml-auto shaven-no-print" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>
    <div class="card-body table-responsive p-0">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th class="text-right">IN</th>
            <th class="text-right">OUT</th>
            <th class="text-right">Balance</th>
            <?php if ($canEdit): ?><th class="shaven-no-print"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$ledger['rows']): ?>
          <tr><td colspan="<?php echo $canEdit ? 6 : 5; ?>">No ledger entries yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($ledger['rows'] as $row): ?>
          <tr<?php echo $editId === (int)$row['id'] ? ' class="table-warning"' : ''; ?>>
            <td class="text-nowrap"><?php echo h(shaven_date($row['entry_date'], 'F j, Y')); ?></td>
            <td>
              <?php echo h($row['description']); ?>
              <div class="text-muted small"><?php echo h($types[$row['entry_type']]['label'] ?? $row['entry_type']); ?></div>
            </td>
            <td class="text-right"><?php echo $row['side'] === 'in' ? shaven_peso($row['amount']) : ''; ?></td>
            <td class="text-right"><?php echo $row['side'] === 'out' ? shaven_peso($row['amount']) : ''; ?></td>
            <td class="text-right text-nowrap"><strong><?php echo shaven_peso($row['balance']); ?></strong></td>
            <?php if ($canEdit): ?>
              <td class="text-right text-nowrap shaven-no-print">
                <a class="btn btn-link btn-sm p-0 mr-2" title="Edit entry" href="<?php echo h(shaven_url('pages/tables/collector_ledger.php?collector=' . (int)$collectorId . '&edit=' . (int)$row['id'])); ?>"><i class="fas fa-pen"></i></a>
                <form method="post" class="d-inline" action="<?php echo h(shaven_url(ltrim($self, '/'))); ?>" onsubmit="return confirm('Delete this ledger entry? The running balance will be recalculated.');">
                  <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="collector" value="<?php echo (int)$collectorId; ?>">
                  <input type="hidden" name="entry_id" value="<?php echo (int)$row['id']; ?>">
                  <button class="btn btn-link btn-sm p-0 text-danger" type="submit" title="Delete entry"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($ledger['rows']): ?>
          <tfoot>
            <tr>
              <th colspan="2" class="text-right">Totals</th>
              <th class="text-right"><?php echo shaven_peso($ledger['in']); ?></th>
              <th class="text-right"><?php echo shaven_peso($ledger['out']); ?></th>
              <th class="text-right"><?php echo shaven_peso($ledger['balance']); ?></th>
              <?php if ($canEdit): ?><th class="shaven-no-print"></th><?php endif; ?>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
<?php endif; ?>
<style>
@media print {
  .shaven-no-print, .main-sidebar, .main-header, .main-footer { display: none !important; }
  .content-wrapper { margin-left: 0 !important; }
}
</style>
<?php shaven_page_close(); ?>
