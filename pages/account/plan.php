<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

if (!shaven_role_is(4)) {
    shaven_redirect('/index.php?denied=1');
}
$subscriberId = (int)($_SESSION['subscriber_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The plan form expired. Submit it again.', 'err');
        shaven_redirect('/pages/account/plan.php');
    }
    $result = shaven_change_plan($conn, $subscriberId, (int)($_POST['plan_id'] ?? 0), (int)$_SESSION['usr_id'], 'Changed from the subscriber portal');
    if ($result === true) {
        shaven_flash_set('Your plan is updated. This month\'s open bill uses the new monthly rate. Older unpaid bills stay at the rate already billed.');
        shaven_redirect('/pages/account/index.php');
    }
    shaven_flash_set($result, 'err');
    shaven_redirect('/pages/account/plan.php');
}

$account = shaven_subscriber_account($conn, $subscriberId);
$plans = shaven_rows($conn, "SELECT id, p_name, p_speed_kb, p_monthly_amount FROM tbl_plans ORDER BY p_monthly_amount, id");
shaven_page_open('Change Plan', 'Change Plan');
if (!$account) {
    echo '<div class="alert alert-warning">This login is not linked to a subscriber record yet.</div>';
    shaven_page_close();
    exit;
}
$currentId = (int)$account['profile']['plan_id'];
?>
<p>The new speed is applied to the account now. This month's open bill follows the new rate. Older unpaid months stay on the rate already billed. <?php echo (int)$account['profile']['status'] === 1 ? 'The line stays active.' : 'An inactive or pending line keeps that status until the office changes it.'; ?></p>
<form method="post">
  <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
  <div class="row">
    <?php foreach ($plans as $plan): ?>
      <?php $isCurrent = (int)$plan['id'] === $currentId; ?>
      <div class="col-md-4">
        <div class="card <?php echo $isCurrent ? 'card-success' : 'card-outline card-primary'; ?>">
          <div class="card-body">
            <h5 class="card-title"><?php echo h($plan['p_name']); ?></h5>
            <p class="h3 mb-1"><?php echo shaven_peso($plan['p_monthly_amount']); ?><small class="text-muted"> / month</small></p>
            <p class="mb-3"><?php echo h(shaven_speed_label($plan['p_speed_kb'])); ?></p>
            <?php if ($isCurrent): ?>
              <button class="btn btn-success" type="button" disabled>Current plan</button>
            <?php else: ?>
              <button class="btn btn-primary" type="submit" name="plan_id" value="<?php echo (int)$plan['id']; ?>">Use this plan</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</form>
<?php shaven_page_close(); ?>
