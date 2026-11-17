<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/account_view.php';
shaven_web_boot(true);

if (!shaven_role_is(4)) {
    shaven_page_open('My Account', 'My Account');
    echo '<div class="alert alert-info">Subscriber payments and plan changes are available on a subscriber login. Office accounts open a subscriber from the subscriber list.</div>';
    shaven_page_close();
    exit;
}

$account = shaven_subscriber_account($conn, (int)($_SESSION['subscriber_id'] ?? 0));
shaven_page_open('My Account', $account ? $account['profile']['s_name'] : 'My Account');
if (!$account) {
    echo '<div class="alert alert-warning">This login is not linked to a subscriber record yet. Ask the office to connect it.</div>';
    shaven_page_close();
    exit;
}
?>
<div class="mb-3">
  <a class="btn btn-primary" href="<?php echo h(shaven_url('pages/account/pay.php')); ?>">Pay bill</a>
  <a class="btn btn-default" href="<?php echo h(shaven_url('pages/account/plan.php')); ?>">Change plan</a>
</div>
<?php
shaven_render_account_stats($account);
shaven_render_account_profile($account);
shaven_render_open_bills($account, false);
shaven_render_payment_history($account);
shaven_render_plan_changes($account);
shaven_page_close();
