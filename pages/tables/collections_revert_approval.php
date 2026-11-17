<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (!shaven_role_is(1, 2)) {
    shaven_redirect('/index.php?denied=1');
}

$userId = (int)($_SESSION['usr_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The approval form expired. Submit it again.', 'err');
        shaven_redirect('/pages/tables/collections_revert_approval.php');
    }
    $action = (string)($_POST['revert_action'] ?? '');
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    if ($action === 'approve') {
        $result = shaven_revert_approve($conn, $userId, $paymentId);
    } elseif ($action === 'close') {
        $result = shaven_revert_close($conn, $userId, $paymentId);
    } else {
        $result = ['ok' => false, 'error' => 'That approval action is not available.'];
    }
    shaven_flash_set($result['error'], $result['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/tables/collections_revert_approval.php');
}

$rows = shaven_revert_approvals($conn, $userId);
$level = shaven_remit_level($conn, $userId);
shaven_page_open('Approve Revert', 'Approve Revert');
?>
<p class="text-muted">An admin or the owner approves a revert while the payment has not been remitted. Approving makes that bill unpaid again. A request you made yourself waits for the other account. A payment that was already remitted stays paid, and the request can be closed.</p>
<div class="card">
  <div class="card-header"><h3 class="card-title">Revert requests</h3></div>
  <div class="card-body p-0">
    <?php shaven_revert_table($rows, $userId, $level, 'approve'); ?>
  </div>
</div>
<?php
shaven_page_close();
