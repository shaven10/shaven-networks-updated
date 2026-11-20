<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);
if (!shaven_role_is(1, 2)) {
    shaven_redirect('/index.php?denied=1');
}

$fromYear = (int)($_POST['from_year'] ?? 0);
$toYear = (int)($_POST['to_year'] ?? 0);
$fromMonth = (int)($_POST['from_month'] ?? 0);
$toMonth = (int)($_POST['to_month'] ?? 0);
$subscriberId = (int)($_POST['subscriber_id'] ?? 0);
$half = (int)($_POST['half'] ?? 0);
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$currentLabel = shaven_month_name($currentMonth) . ' ' . $currentYear;
$plan = null;
$billingSubscribers = shaven_rows(
    $conn,
    "SELECT id, s_name, account_no FROM tbl_subs_profile WHERE status=1 ORDER BY s_name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The billing form expired. Submit it again.', 'err');
        shaven_redirect('/pages/admin/billing.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'current_generate' || $action === 'current_preview') {
        if ($half !== 1 && $half !== 2) {
            shaven_flash_set('Choose the 1st half or the 2nd half of the month.', 'err');
            shaven_redirect('/pages/admin/billing.php');
        }
        if ($action === 'current_preview') {
            $plan = shaven_billing_plan($conn, $currentYear, $currentMonth, $currentYear, $currentMonth, $subscriberId, $half);
        } else {
            $result = shaven_billing_generate($conn, $currentYear, $currentMonth, $currentYear, $currentMonth, $subscriberId, $half);
            $scope = $currentLabel . ', ' . shaven_billing_half_label($half);
            if (!$result['ok']) {
                shaven_flash_set($result['error'], 'err');
            } elseif ((int)$result['created'] === 0) {
                shaven_flash_set('No bills were created for ' . $scope . '. ' . (int)$result['skipped'] . ' already have a bill.');
            } else {
                shaven_flash_set('Created ' . (int)$result['created'] . ' bill' . ((int)$result['created'] === 1 ? '' : 's') . ' for ' . $scope . '. ' . (int)$result['skipped'] . ' already had a bill and were skipped.');
            }
            shaven_redirect('/pages/admin/billing.php');
        }
    }
    if ($action === 'generate') {
        $result = shaven_billing_generate($conn, $fromYear, $fromMonth, $toYear, $toMonth, $subscriberId);
        $who = trim((string)($result['subscriber'] ?? ''));
        $who = $who !== '' ? ' for ' . $who : '';
        if (!$result['ok']) {
            shaven_flash_set($result['error'], 'err');
        } elseif ((int)$result['created'] === 0) {
            shaven_flash_set('No bills were created' . $who . '. ' . (int)$result['skipped'] . ' periods already have a bill.');
        } else {
            shaven_flash_set('Created ' . (int)$result['created'] . ' bill' . ((int)$result['created'] === 1 ? '' : 's') . $who . '. ' . (int)$result['skipped'] . ' periods already had a bill and were skipped.');
        }
        shaven_redirect('/pages/admin/billing.php');
    }
    if ($action === 'preview') {
        $plan = shaven_billing_plan($conn, $fromYear, $fromMonth, $toYear, $toMonth, $subscriberId);
    }
}

function shaven_billing_month_options($selected) {
    echo '<option value="">Month</option>';
    for ($month = 1; $month <= 12; $month++) {
        $name = shaven_month_name($month);
        echo '<option value="' . $month . '"' . ((int)$selected === $month ? ' selected' : '') . '>' . h($name) . '</option>';
    }
}

shaven_page_open('Billing', 'Installation billing');
?>
<div class="card">
  <div class="card-header"><h3 class="card-title">How a bill is built</h3></div>
  <div class="card-body">
    <p class="mb-2">Each active subscriber is billed from the installation date. A period runs from that day through the day before the same day next month. The billing month is the due-date month: someone installed on January 12, 2026 has a first billing month of February 2026, inclusive dates January 12, 2026 through February 11, 2026, and a due date of February 12, 2026, one day after the period ends.</p>
    <p class="mb-2">Bills are never created automatically. Nothing is billed until an admin or the owner generates it on this page.</p>
    <p class="mb-0">Only active subscribers are included. Choose one subscriber to bill that line alone, or leave the subscriber blank to include every active line. Billing months that already have a bill are skipped. Leave both years empty to create every missing billing month whose period has already started, through the current month. To limit the run, set an inclusive range. The range uses the billing month (due-date month), not the period start.</p>
  </div>
</div>
<div class="card card-success">
  <div class="card-header"><h3 class="card-title">Current month update · <?php echo h($currentLabel); ?></h3></div>
  <form method="post" id="billing-current-form">
    <div class="card-body">
      <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
      <p class="text-muted">Creates the <?php echo h($currentLabel); ?> bill for active subscribers whose due date falls in the half you choose. Subscribers who already have a <?php echo h($currentLabel); ?> bill are skipped.</p>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label for="billing-half">Due dates</label>
          <select class="form-control" id="billing-half" name="half" required>
            <option value="">Choose a half</option>
            <option value="1" <?php echo $half === 1 ? 'selected' : ''; ?>>1st half · due on days 1–15</option>
            <option value="2" <?php echo $half === 2 ? 'selected' : ''; ?>>2nd half · due on days 16–31</option>
          </select>
        </div>
        <div class="form-group col-md-6">
          <label for="billing-current-subscriber">Subscriber</label>
          <select class="form-control" id="billing-current-subscriber" name="subscriber_id">
            <option value="">All active subscribers</option>
            <?php foreach ($billingSubscribers as $subscriber): ?>
              <?php
                $subscriberLabel = (string)$subscriber['s_name'];
                if (trim((string)($subscriber['account_no'] ?? '')) !== '') {
                    $subscriberLabel .= ' · ' . $subscriber['account_no'];
                }
              ?>
              <option value="<?php echo (int)$subscriber['id']; ?>" <?php echo $half > 0 && $subscriberId === (int)$subscriber['id'] ? 'selected' : ''; ?>><?php echo h($subscriberLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button class="btn btn-primary" type="submit" name="action" value="current_preview">Preview <?php echo h($currentLabel); ?> bills</button>
      <button class="btn btn-success" type="submit" id="billing-current-generate" name="action" value="current_generate">Generate <?php echo h($currentLabel); ?> bills</button>
    </div>
  </form>
</div>
<div class="card card-primary">
  <div class="card-header"><h3 class="card-title">Generate missing bills</h3></div>
  <form method="post">
    <div class="card-body">
      <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
      <div class="form-group">
        <label for="billing-subscriber">Subscriber</label>
        <select class="form-control" id="billing-subscriber" name="subscriber_id">
          <option value="">All active subscribers</option>
          <?php foreach ($billingSubscribers as $subscriber): ?>
            <?php
              $subscriberLabel = (string)$subscriber['s_name'];
              if (trim((string)($subscriber['account_no'] ?? '')) !== '') {
                  $subscriberLabel .= ' · ' . $subscriber['account_no'];
              }
            ?>
            <option value="<?php echo (int)$subscriber['id']; ?>" <?php echo $half === 0 && $subscriberId === (int)$subscriber['id'] ? 'selected' : ''; ?>><?php echo h($subscriberLabel); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label>From year</label>
          <input class="form-control" name="from_year" type="number" min="1990" max="2100" placeholder="All years" value="<?php echo $fromYear > 0 ? (int)$fromYear : ''; ?>">
        </div>
        <div class="form-group col-md-3">
          <label>From month</label>
          <select class="form-control" name="from_month"><?php shaven_billing_month_options($fromMonth); ?></select>
        </div>
        <div class="form-group col-md-3">
          <label>Through year</label>
          <input class="form-control" name="to_year" type="number" min="1990" max="2100" placeholder="Current month" value="<?php echo $toYear > 0 ? (int)$toYear : ''; ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Through month</label>
          <select class="form-control" name="to_month"><?php shaven_billing_month_options($toMonth); ?></select>
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button class="btn btn-primary" type="submit" name="action" value="preview">Preview missing bills</button>
      <button class="btn btn-success" type="submit" id="billing-generate" name="action" value="generate">Generate bills</button>
    </div>
  </form>
</div>
<?php if (is_array($plan)): ?>
  <?php if (!$plan['ok']): ?>
    <div class="alert alert-danger"><?php echo h($plan['error']); ?></div>
  <?php else: ?>
    <?php
      $amount = 0.0;
      foreach ($plan['create'] as $bill) {
          $amount += (float)$bill['amount'];
      }
    ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Preview for <?php echo h($plan['label']); ?></h3></div>
      <div class="card-body">
        <p class="mb-2"><?php echo (int)$plan['active']; ?> active subscriber<?php echo (int)$plan['active'] === 1 ? ' was' : 's were'; ?> checked. <?php echo count($plan['create']); ?> missing periods can be created, totaling <?php echo shaven_peso($amount); ?>. <?php echo (int)$plan['skipped']; ?> periods already have a bill and will be skipped.</p>
        <?php if ((int)$plan['no_date'] > 0 || (int)$plan['no_amount'] > 0): ?>
          <p class="mb-0 text-muted"><?php echo (int)$plan['no_date']; ?> active subscribers have no installation date. <?php echo (int)$plan['no_amount']; ?> have no monthly amount.</p>
        <?php endif; ?>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>Subscriber</th><th>Installed</th><th>Billing month</th><th>Billing period</th><th>Due date</th><th>Amount</th></tr></thead>
          <tbody>
          <?php if (!$plan['create']): ?>
            <tr><td colspan="6">Every period in this range already has a bill.</td></tr>
          <?php endif; ?>
          <?php
            $preview = array_slice($plan['create'], 0, 25);
            $previewName = null;
            foreach ($preview as $bill):
              if ($bill['name'] !== $previewName):
                $previewName = $bill['name'];
                $previewCount = 0;
                $previewAmount = 0.0;
                foreach ($preview as $member) {
                    if ($member['name'] === $previewName) {
                        $previewCount++;
                        $previewAmount += (float)$member['amount'];
                    }
                }
          ?>
            <tr class="shaven-bill-group">
              <td colspan="6">
                <div class="shaven-bill-group">
                  <span class="shaven-group-name"><?php echo h($previewName); ?></span>
                  <span class="shaven-group-meta"><?php echo (int)$previewCount; ?> <?php echo $previewCount === 1 ? 'record' : 'records'; ?> · <?php echo shaven_peso($previewAmount); ?></span>
                </div>
              </td>
            </tr>
          <?php endif; ?>
            <tr>
              <td><?php echo h($bill['name']); ?></td>
              <td><?php echo h(shaven_date($bill['installed'], 'F j, Y')); ?></td>
              <td><?php echo h(shaven_billing_month_label($bill)); ?></td>
              <td><?php echo h(shaven_date($bill['start'], 'F j, Y') . ' – ' . shaven_date($bill['end'], 'F j, Y')); ?></td>
              <td><span class="shaven-due-date"><?php echo h(shaven_date($bill['due'], 'F j, Y')); ?></span></td>
              <td><?php echo shaven_peso($bill['amount']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($plan['create']) > 25): ?>
        <div class="card-footer"><?php echo count($plan['create']) - 25; ?> more periods are included when you generate the bills.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<script>
document.getElementById('billing-generate').addEventListener('click', function (event) {
  var select = document.getElementById('billing-subscriber');
  var name = select && select.value ? select.options[select.selectedIndex].text : 'every active subscriber';
  if (!confirm('Create the missing bills for ' + name + '? Periods that already have a bill are skipped.')) {
    event.preventDefault();
  }
});
document.getElementById('billing-current-generate').addEventListener('click', function (event) {
  var half = document.getElementById('billing-half');
  if (!half.value) {
    return;
  }
  var select = document.getElementById('billing-current-subscriber');
  var name = select && select.value ? select.options[select.selectedIndex].text : 'every active subscriber';
  if (!confirm('Create the <?php echo h($currentLabel); ?> bills (' + half.options[half.selectedIndex].text + ') for ' + name + '? Subscribers who already have this bill are skipped.')) {
    event.preventDefault();
  }
});
</script>
<?php shaven_page_close(); ?>
