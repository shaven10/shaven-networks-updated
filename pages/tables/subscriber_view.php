<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/account_view.php';
shaven_web_boot(true);
if (!shaven_role_is(1, 2, 3)) {
    shaven_redirect('/index.php?denied=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_POST['uid'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update_amount' || $action === 'delete_bill') {
        $back = '/pages/tables/subscriber_view.php?uid=' . $uid;
        if (shaven_role() !== 1 || !shaven_csrf_ok()) {
            shaven_flash_set($action === 'delete_bill' ? 'Only the owner can delete a bill.' : 'Only the owner can change a bill.', 'err');
            shaven_redirect($back);
        }
        $result = $action === 'delete_bill'
            ? shaven_owner_delete_bill($conn, $uid, (int)($_POST['bill_id'] ?? 0))
            : shaven_owner_update_bill_amount($conn, $uid, (int)($_POST['bill_id'] ?? 0), $_POST['amount'] ?? '');
        shaven_flash_set($result['message'], $result['ok'] ? 'ok' : 'err');
        shaven_redirect($back);
    }
    if (!shaven_role_is(1, 2) || !shaven_csrf_ok()) {
        shaven_flash_set('The portal login was not changed.', 'err');
        shaven_redirect('/pages/tables/reports.php');
    }
    if ($action === 'provision') {
        $result = shaven_provision_subscriber($conn, $uid);
        if ($result['ok']) {
            shaven_flash_set('Portal login created. Username: ' . $result['username'] . '. Temporary password: ' . $result['password'] . '. Share it with the subscriber now.');
        } else {
            shaven_flash_set($result['error'], 'err');
        }
    } elseif ($action === 'reset') {
        $result = shaven_reset_portal_password($conn, $uid);
        if ($result['ok']) {
            shaven_flash_set('Password reset for ' . $result['username'] . '. Temporary password: ' . $result['password'] . '.');
        } else {
            shaven_flash_set($result['error'], 'err');
        }
    }
    shaven_redirect('/pages/tables/subscriber_view.php?uid=' . $uid);
}

$uid = (int)($_GET['uid'] ?? 0);
$account = $uid > 0 ? shaven_subscriber_account($conn, $uid) : null;
shaven_page_open('Subscriber', $account ? $account['profile']['s_name'] : 'Subscriber');
if (!$account) {
    echo '<div class="alert alert-warning">No subscriber matches that account number.</div>';
    echo '<a class="btn btn-default" href="' . h(shaven_url('pages/tables/reports.php')) . '">Back to subscribers</a>';
    shaven_page_close();
    exit;
}
$profile = $account['profile'];
$id = (int)$profile['id'];
?>
<div class="mb-3">
  <a class="btn btn-primary" href="<?php echo h(shaven_url('pages/tables/collections.php?uid=' . $id . '#pay-desk')); ?>">Post Payment</a>
  <a class="btn btn-default" href="<?php echo h(shaven_url('pages/tables/reports.php')); ?>">All subscribers</a>
  <?php if ((int)$profile['status'] === 1): ?>
    <a class="btn btn-danger" href="<?php echo h(shaven_url('pages/tables/sub_deactivate.php?uid=' . $id)); ?>" onclick="return confirm('Deactivate this subscriber?');">Deactivate</a>
  <?php else: ?>
    <a class="btn btn-success" href="<?php echo h(shaven_url('pages/tables/sub_activate.php?uid=' . $id)); ?>" onclick="return confirm('Activate this subscriber?');">Activate</a>
  <?php endif; ?>
  <?php if (shaven_role_is(1, 2)): ?>
    <a class="btn btn-primary" href="<?php echo h(shaven_url('pages/forms/update_plan.php?uid=' . $id)); ?>">Change plan</a>
  <?php endif; ?>
</div>
<?php
shaven_render_account_stats($account);
shaven_render_account_profile($account);
?>
<div class="card">
  <div class="card-header"><h3 class="card-title">Portal login</h3></div>
  <div class="card-body">
    <?php if ($account['portal']): ?>
      <p>Username <strong><?php echo h($account['portal']['username']); ?></strong> can sign in, pay open bills, and change this plan.</p>
      <?php if (shaven_role_is(1, 2)): ?>
        <form method="post" class="d-inline">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="uid" value="<?php echo $id; ?>">
          <input type="hidden" name="action" value="reset">
          <button class="btn btn-default" type="submit">Reset portal password</button>
        </form>
      <?php endif; ?>
    <?php elseif (shaven_role_is(1, 2)): ?>
      <p>This subscriber can pay and change plans after a portal login is created.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
        <input type="hidden" name="uid" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="provision">
        <button class="btn btn-primary" type="submit">Create portal login</button>
      </form>
    <?php else: ?>
      <p>No portal login is linked yet. An admin can create one from this page.</p>
    <?php endif; ?>
  </div>
</div>
<?php
$ownerTools = shaven_role() === 1;
shaven_render_open_bills($account, false, $ownerTools);
shaven_render_payment_history($account, $ownerTools);
shaven_render_plan_changes($account);
shaven_page_close();
