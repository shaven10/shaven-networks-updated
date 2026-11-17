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
    <p class="mb-2">Each active subscriber is billed from the installation date. A period runs from that day through the day before the same day next month. Someone installed on January 15 is billed January 15 through February 14, and the due date is February 15, one day after the period ends.</p>
    <p class="mb-0">Only active subscribers are included. Choose one subscriber to bill that line alone, or leave the subscriber blank to include every active line. Periods that already have a bill are skipped. Leave both years empty to create every missing period that has already started, through the current month. To limit the run, set an inclusive range. The range uses the year and month when the period starts, not the due date.</p>
  </div>
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
            <option value="<?php echo (int)$subscriber['id']; ?>" <?php echo $subscriberId === (int)$subscriber['id'] ? 'selected' : ''; ?>><?php echo h($subscriberLabel); ?></option>
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
          <thead><tr><th>Subscriber</th><th>Installed</th><th>Billing period</th><th>Due date</th><th>Amount</th></tr></thead>
          <tbody>
          <?php if (!$plan['create']): ?>
            <tr><td colspan="5">Every period in this range already has a bill.</td></tr>
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
              <td colspan="5">
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
</script>
<?php shaven_page_close(); ?>
