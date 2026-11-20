<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (!shaven_db_tools_can()) {
    shaven_redirect('/index.php?denied=1');
}

$userId = (int)($_SESSION['usr_id'] ?? 0);
shaven_db_tools_ensure_dirs();

if (isset($_GET['download'])) {
    $kind = (string)($_GET['kind'] ?? 'backup');
    $result = shaven_db_tools_download($kind === 'snapshot' ? 'snapshot' : 'backup', (string)($_GET['download'] ?? ''));
    if (!$result['ok']) {
        shaven_flash_set($result['error'], 'err');
        shaven_redirect('/pages/admin/db_tools.php');
    }
    $path = $result['path'];
    $file = $result['file'];
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file) . '"');
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The database tools form expired. Submit it again.', 'err');
        shaven_redirect('/pages/admin/db_tools.php');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'backup') {
        $result = shaven_db_tools_create_backup($conn, $userId, (string)($_POST['backup_label'] ?? ''));
    } elseif ($action === 'snapshot') {
        $result = shaven_db_tools_create_snapshot($conn, $userId, (string)($_POST['snapshot_label'] ?? ''));
    } elseif ($action === 'restore_backup') {
        if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'RESTORE') {
            $result = ['ok' => false, 'error' => 'Type RESTORE to confirm restoring a full backup.'];
        } else {
            $result = shaven_db_tools_restore_file($conn, 'backup', (string)($_POST['file'] ?? ''));
        }
    } elseif ($action === 'restore_snapshot') {
        if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'RESTORE POINT') {
            $result = ['ok' => false, 'error' => 'Type RESTORE POINT to confirm restoring a snapshot.'];
        } else {
            $result = shaven_db_tools_restore_file($conn, 'snapshot', (string)($_POST['file'] ?? ''));
        }
    } elseif ($action === 'upload_restore') {
        if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'RESTORE') {
            $result = ['ok' => false, 'error' => 'Type RESTORE to confirm restoring an uploaded backup.'];
        } else {
            $result = shaven_db_tools_restore_upload($conn, $_FILES['backup_file'] ?? []);
        }
    } elseif ($action === 'reset_transactions') {
        $result = shaven_db_tools_reset_transactions($conn, $userId, (string)($_POST['confirm'] ?? ''));
    } elseif ($action === 'delete_backup') {
        $result = shaven_db_tools_delete_file('backup', (string)($_POST['file'] ?? ''));
    } elseif ($action === 'delete_snapshot') {
        $result = shaven_db_tools_delete_file('snapshot', (string)($_POST['file'] ?? ''));
    } else {
        $result = ['ok' => false, 'error' => 'That database tool action is not available.'];
    }
    shaven_flash_set($result['error'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/admin/db_tools.php');
}

$counts = shaven_db_tools_counts($conn);
$backups = shaven_db_tools_list_files(shaven_db_tools_backup_dir(), 'backup');
$snapshots = shaven_db_tools_list_files(shaven_db_tools_snapshot_dir(), 'snapshot');
$preserve = shaven_db_tools_preserve_tables();
$transactions = shaven_db_tools_transaction_tables();

shaven_page_open('Database Tools', 'Database Tools');
?>
<p class="text-muted">Owner and admin tools for full backups, restore-point snapshots, and clearing payment and remittance data. Subscriber profiles and plans are kept on a transaction reset.</p>

<div class="row">
  <div class="col-lg-6">
    <div class="card card-outline card-primary">
      <div class="card-header"><h3 class="card-title">Current counts</h3></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Table</th><th class="text-right">Rows</th><th>Kept on reset</th></tr></thead>
          <tbody>
          <?php foreach ($counts as $table => $count): ?>
            <tr>
              <td><code><?php echo h($table); ?></code></td>
              <td class="text-right"><?php echo (int)$count; ?></td>
              <td><?php echo in_array($table, $preserve, true) ? 'Yes' : 'Cleared'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card card-outline card-secondary">
      <div class="card-header"><h3 class="card-title">What each tool does</h3></div>
      <div class="card-body">
        <p class="mb-2"><strong>Full backup</strong> saves every table to a .sql file you can download or restore later.</p>
        <p class="mb-2"><strong>Restore point</strong> snapshots payments, remittances, remittance batches, finances, plan-change history, and notifications only.</p>
        <p class="mb-0"><strong>Reset transactions</strong> clears those transaction tables and keeps <?php echo h(implode(', ', $preserve)); ?>. An automatic restore point is created first.</p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-6">
    <div class="card card-primary">
      <div class="card-header"><h3 class="card-title">Create full backup</h3></div>
      <form method="post">
        <div class="card-body">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="action" value="backup">
          <div class="form-group mb-0">
            <label for="backup-label">Optional label</label>
            <input class="form-control" id="backup-label" name="backup_label" maxlength="80" placeholder="Before month-end close">
          </div>
        </div>
        <div class="card-footer">
          <button class="btn btn-primary" type="submit">Create backup</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card card-info">
      <div class="card-header"><h3 class="card-title">Create restore point</h3></div>
      <form method="post">
        <div class="card-body">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="action" value="snapshot">
          <div class="form-group mb-0">
            <label for="snapshot-label">Restore point name</label>
            <input class="form-control" id="snapshot-label" name="snapshot_label" maxlength="80" required placeholder="Before remittance cleanup">
          </div>
        </div>
        <div class="card-footer">
          <button class="btn btn-info" type="submit">Create restore point</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card card-danger">
  <div class="card-header"><h3 class="card-title">Full reset of transactions and remittances</h3></div>
  <form method="post" onsubmit="return confirm('Clear all payments, remittances, batches, finances, plan-change history, and notifications? Subscriber profiles and plans stay.');">
    <div class="card-body">
      <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
      <input type="hidden" name="action" value="reset_transactions">
      <p class="mb-2">Clears: <?php echo implode(', ', array_map(function ($table) { return '<code>' . h($table) . '</code>'; }, $transactions)); ?></p>
      <p class="mb-2">Keeps: <?php echo implode(', ', array_map(function ($table) { return '<code>' . h($table) . '</code>'; }, $preserve)); ?></p>
      <div class="form-group mb-0" style="max-width:24rem;">
        <label for="reset-confirm">Type <strong>RESET TRANSACTIONS</strong> to confirm</label>
        <input class="form-control" id="reset-confirm" name="confirm" required autocomplete="off">
      </div>
    </div>
    <div class="card-footer">
      <button class="btn btn-danger" type="submit">Reset transactions</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title">Saved backups</h3></div>
  <div class="card-body table-responsive p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Label</th>
          <th>File</th>
          <th>Created</th>
          <th class="text-right">Size</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$backups): ?>
        <tr><td colspan="5">No backups yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($backups as $item): ?>
        <tr>
          <td><?php echo h($item['label']); ?></td>
          <td><code><?php echo h($item['file']); ?></code></td>
          <td><?php echo h(shaven_date($item['created_at'], 'M j, Y g:i A')); ?></td>
          <td class="text-right"><?php echo number_format(((int)$item['bytes']) / 1024, 1); ?> KB</td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-default" href="<?php echo h(shaven_url('pages/admin/db_tools.php?kind=backup&download=' . rawurlencode($item['file']))); ?>">Download</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Restore this full backup? Current data will be replaced from the file.');">
              <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
              <input type="hidden" name="action" value="restore_backup">
              <input type="hidden" name="file" value="<?php echo h($item['file']); ?>">
              <input type="hidden" name="confirm" value="RESTORE">
              <button class="btn btn-sm btn-warning" type="submit">Restore</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this backup file?');">
              <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
              <input type="hidden" name="action" value="delete_backup">
              <input type="hidden" name="file" value="<?php echo h($item['file']); ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title">Restore points</h3></div>
  <div class="card-body table-responsive p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>File</th>
          <th>Created</th>
          <th class="text-right">Size</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$snapshots): ?>
        <tr><td colspan="5">No restore points yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($snapshots as $item): ?>
        <tr>
          <td><?php echo h($item['label']); ?></td>
          <td><code><?php echo h($item['file']); ?></code></td>
          <td><?php echo h(shaven_date($item['created_at'], 'M j, Y g:i A')); ?></td>
          <td class="text-right"><?php echo number_format(((int)$item['bytes']) / 1024, 1); ?> KB</td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-default" href="<?php echo h(shaven_url('pages/admin/db_tools.php?kind=snapshot&download=' . rawurlencode($item['file']))); ?>">Download</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Restore this point? Payments and remittances will be replaced from the snapshot. Profiles and plans stay.');">
              <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
              <input type="hidden" name="action" value="restore_snapshot">
              <input type="hidden" name="file" value="<?php echo h($item['file']); ?>">
              <input type="hidden" name="confirm" value="RESTORE POINT">
              <button class="btn btn-sm btn-warning" type="submit">Restore point</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this restore point?');">
              <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
              <input type="hidden" name="action" value="delete_snapshot">
              <input type="hidden" name="file" value="<?php echo h($item['file']); ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card card-warning">
  <div class="card-header"><h3 class="card-title">Upload and restore a .sql backup</h3></div>
  <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Restore the uploaded backup? This replaces data from the SQL file.');">
    <div class="card-body">
      <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
      <input type="hidden" name="action" value="upload_restore">
      <div class="form-group">
        <label for="backup-file">.sql file</label>
        <input class="form-control-file" id="backup-file" type="file" name="backup_file" accept=".sql,text/sql,application/sql" required>
      </div>
      <div class="form-group mb-0" style="max-width:24rem;">
        <label for="upload-confirm">Type <strong>RESTORE</strong> to confirm</label>
        <input class="form-control" id="upload-confirm" name="confirm" required autocomplete="off">
      </div>
    </div>
    <div class="card-footer">
      <button class="btn btn-warning" type="submit">Upload and restore</button>
    </div>
  </form>
</div>
<?php
shaven_page_close();
