<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/account_view.php';
shaven_web_boot(true);

if (!shaven_role_is(4)) {
    shaven_redirect('/index.php?denied=1');
}
$subscriberId = (int)($_SESSION['subscriber_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The payment form expired. Submit it again.', 'err');
        shaven_redirect('/pages/account/pay.php');
    }
    if (isset($_POST['pay_all'])) {
        $open = shaven_rows($conn, "SELECT id FROM tbl_payments WHERE payment_uid=? AND status=0", 'i', [$subscriberId]);
        $ids = array_column($open, 'id');
    } else {
        $ids = $_POST['bill_ids'] ?? [];
    }
    $result = shaven_pay_bills($conn, $subscriberId, $ids, (int)$_SESSION['usr_id']);
    if (!$result['ok']) {
        shaven_flash_set($result['error'], 'err');
        shaven_redirect('/pages/account/pay.php');
    }
    $count = count($result['receipts']);
    $ids = [];
    foreach ($result['receipts'] as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    shaven_flash_set('Payment posted for ' . $count . ' bill' . ($count === 1 ? '' : 's') . '.', 'ok');
    if ($ids) {
        shaven_redirect('/pages/tables/receipt.php?ids=' . implode(',', $ids));
    }
    shaven_redirect('/pages/account/index.php');
}

$account = shaven_subscriber_account($conn, $subscriberId);
shaven_page_open('Pay Bill', 'Pay Bill');
if (!$account) {
    echo '<div class="alert alert-warning">This login is not linked to a subscriber record yet.</div>';
    shaven_page_close();
    exit;
}
?>
<p>Payments post to your open bills and a receipt is stored with the account. Portal payments stay on the subscriber record and are kept out of the collector remittance wallet.</p>
<?php if (!$account['open_bills']): ?>
  <div class="alert alert-success">Your account has no open bill.</div>
  <a class="btn btn-default" href="<?php echo h(shaven_url('pages/account/index.php')); ?>">Back to my account</a>
<?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
    <?php shaven_render_open_bills($account, true); ?>
    <div class="mb-3">
      <strong>Selected total: &#8369;<span id="selected-total">0.00</span></strong>
    </div>
    <button class="btn btn-primary" type="submit" name="pay_selected" value="1">Pay selected</button>
    <button class="btn btn-default" type="submit" name="pay_all" value="1" onclick="return confirm('Post payment for every open bill?');">Pay all open bills</button>
  </form>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    function recalc() {
      var total = 0;
      document.querySelectorAll('.bill-check:checked').forEach(function (el) {
        total += parseFloat(el.getAttribute('data-amount') || '0');
      });
      var node = document.getElementById('selected-total');
      if (node) node.textContent = total.toFixed(2);
    }
    document.querySelectorAll('.bill-check').forEach(function (el) {
      el.addEventListener('change', recalc);
    });
    recalc();
  });
  </script>
<?php endif; ?>
<?php shaven_page_close(); ?>
