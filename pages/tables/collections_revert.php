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
        shaven_flash_set('The revert form expired. Submit it again.', 'err');
        shaven_redirect('/pages/tables/collections_revert.php');
    }
    $action = (string)($_POST['revert_action'] ?? '');
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    if ($action === 'request') {
        $result = shaven_revert_request($conn, $userId, $paymentId);
    } elseif ($action === 'cancel') {
        $result = shaven_revert_cancel($conn, $userId, $paymentId);
    } else {
        $result = ['ok' => false, 'error' => 'That revert action is not available.'];
    }
    shaven_flash_set($result['error'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/tables/collections_revert.php');
}

$rows = shaven_revert_queue($conn, $userId);
$level = shaven_remit_level($conn, $userId);
shaven_page_open('Revert Payment', 'Revert Payment');
?>
<p class="text-muted">A paid bill can be reverted only while the payment has not been remitted. The request waits for an admin or the owner, and the person who asked does not approve it.<?php echo shaven_role_is(3) ? ' This list is the payments posted on your account.' : ''; ?></p>
<div class="form-group" style="max-width:24rem;">
  <label for="revert-find">Find</label>
  <input id="revert-find" class="form-control" type="search" placeholder="Name, account, or period">
</div>
<div class="card">
  <div class="card-header"><h3 class="card-title">Paid bills that have not been remitted</h3></div>
  <div class="card-body p-0">
    <?php shaven_revert_table($rows, $userId, $level, 'ask'); ?>
  </div>
</div>
<script>
document.getElementById('revert-find').addEventListener('input', function () {
  var query = this.value.trim().toLowerCase();
  document.querySelectorAll('[data-revert-row]').forEach(function (row) {
    var show = query === '' || (row.getAttribute('data-find') || '').indexOf(query) !== -1;
    row.classList.toggle('d-none', !show);
  });
});
</script>
<?php
shaven_page_close();
