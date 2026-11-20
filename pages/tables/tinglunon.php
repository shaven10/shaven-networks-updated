<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/tinglunon.php';
shaven_web_boot(true);

if (!shaven_role_is(1, 2, 3)) {
    shaven_redirect('/index.php?denied=1');
}

$report = shaven_tinglunon_report($conn);
$asOfLabel = shaven_date($report['as_of'], 'F j, Y');
$stamp = date('F j, Y g:i A');
$who = trim((string)($_SESSION['fullname'] ?? 'Account')) . ' · ' . shaven_role_name();

function shaven_tinglunon_find(array $parts) {
    $text = strtolower(trim(preg_replace('/\s+/', ' ', implode(' ', $parts))));
    return $text;
}

shaven_page_open('Tinglunon', 'Tinglunon');
?>
<p class="text-muted">Pending bills whose due date is <?php echo h($asOfLabel); ?> or earlier. Active and pending accounts only, earliest due date first. Each subscriber starts closed. Click a name to show or hide that subscriber’s bills. PNG and JPEG save every bill still on this sheet, including bills that are closed.</p>
<div class="d-flex flex-wrap align-items-end mb-3">
  <div class="form-group mr-3 mb-2">
    <label for="tinglunon-find">Find</label>
    <input id="tinglunon-find" class="form-control" type="search" placeholder="Name, account, status, payment, or period">
  </div>
  <div class="btn-group mb-2 mr-3">
    <button class="btn btn-primary" type="button" data-tinglunon-export="png" <?php echo $report['count'] ? '' : 'disabled'; ?>><i class="fas fa-image"></i> Export PNG</button>
    <button class="btn btn-default" type="button" data-tinglunon-export="jpeg" <?php echo $report['count'] ? '' : 'disabled'; ?>><i class="fas fa-file-image"></i> Export JPEG</button>
  </div>
  <p id="tinglunon-export-note" class="text-muted mb-2"></p>
</div>
<div id="tinglunon-sheet" class="tinglunon-sheet mb-3" data-stamp="<?php echo h($stamp); ?>" data-asof="<?php echo h($asOfLabel); ?>" data-who="<?php echo h($who); ?>">
  <div class="tinglunon-banner">
    <div class="text-uppercase small" style="letter-spacing:.08em;">SHAVEN Networks</div>
    <h2>Tinglunon</h2>
    <p>Due on or before <?php echo h($asOfLabel); ?> · <?php echo h($stamp); ?></p>
    <p><?php echo h($who); ?></p>
    <p id="tinglunon-summary" data-subscribers="<?php echo (int)$report['subscribers']; ?>" data-bills="<?php echo (int)$report['count']; ?>" data-total="<?php echo h(html_entity_decode(shaven_peso($report['total']), ENT_QUOTES, 'UTF-8')); ?>">
      <?php echo (int)$report['subscribers']; ?> subscriber<?php echo (int)$report['subscribers'] === 1 ? '' : 's'; ?>
      · <?php echo (int)$report['count']; ?> pending bill<?php echo (int)$report['count'] === 1 ? '' : 's'; ?>
      · <?php echo shaven_peso($report['total']); ?>
    </p>
  </div>
  <?php if (!$report['groups']): ?>
    <p class="p-3 mb-0">No bills are due through <?php echo h($asOfLabel); ?>.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Account</th>
            <th>Subscriber</th>
            <th>Status</th>
            <th>Payment status</th>
            <th>Billing month</th>
            <th>Billing period</th>
            <th>Due date</th>
            <th class="tinglunon-money">Amount</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($report['groups'] as $group): ?>
          <?php
            $statusLabel = (int)$group['status'] === 1 ? 'Active' : ((int)$group['status'] === 2 ? 'Inactive' : 'Pending');
            $paymentLabel = (string)($group['payment_status'] ?? 'Unpaid');
            $detailParts = array_filter([$group['address'], $group['plan'], $group['count'] . ' bill' . ($group['count'] === 1 ? '' : 's')]);
            $detail = implode(' · ', $detailParts);
            $amountLabel = html_entity_decode(shaven_peso($group['total']), ENT_QUOTES, 'UTF-8');
          ?>
          <tr class="tinglunon-line tinglunon-group shaven-group-collapsed"
              data-kind="group"
              role="button"
              tabindex="0"
              aria-expanded="false"
              title="Show records"
              data-subscriber="<?php echo (int)$group['id']; ?>"
              data-account="<?php echo h($group['account']); ?>"
              data-name="<?php echo h($group['name']); ?>"
              data-detail="<?php echo h($detail); ?>"
              data-status="<?php echo h($statusLabel); ?>"
              data-payment="<?php echo h($paymentLabel); ?>"
              data-amount-label="<?php echo h($amountLabel); ?>"
              data-find="<?php echo h(shaven_tinglunon_find([$group['name'], $group['account'], $group['address'], $group['plan'], $statusLabel, $paymentLabel])); ?>">
            <td><span class="shaven-account-no"><?php echo h($group['account'] !== '' ? $group['account'] : 'No account number'); ?></span></td>
            <td>
              <strong class="shaven-group-name"><?php echo h($group['name']); ?></strong>
              <div class="text-muted small"><?php echo h($detail); ?></div>
            </td>
            <td class="tinglunon-status"><?php echo shaven_status_badge($group['status']); ?></td>
            <td class="tinglunon-status"><?php echo shaven_tinglunon_payment_badge($paymentLabel); ?></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="tinglunon-money"><strong><?php echo shaven_peso($group['total']); ?></strong></td>
          </tr>
          <?php foreach ($group['bills'] as $bill): ?>
            <?php
              $billAmount = html_entity_decode(shaven_peso($bill['amount']), ENT_QUOTES, 'UTF-8');
              $billPayment = (string)($bill['payment_status'] ?? 'Unpaid');
              $billMonth = (string)($bill['month'] ?? '');
            ?>
            <tr class="tinglunon-line shaven-group-member-hidden"
                data-kind="bill"
                data-subscriber="<?php echo (int)$group['id']; ?>"
                data-month="<?php echo h($billMonth); ?>"
                data-period="<?php echo h($bill['period']); ?>"
                data-due="<?php echo h($bill['due_label']); ?>"
                data-status="<?php echo h($statusLabel); ?>"
                data-payment="<?php echo h($billPayment); ?>"
                data-amount="<?php echo h(number_format((float)$bill['amount'], 2, '.', '')); ?>"
                data-amount-label="<?php echo h($billAmount); ?>"
                data-find="<?php echo h(shaven_tinglunon_find([$group['name'], $group['account'], $statusLabel, $billPayment, $billMonth, $bill['period'], $bill['due_label']])); ?>">
              <td></td>
              <td></td>
              <td class="tinglunon-status"><?php echo shaven_status_badge($group['status']); ?></td>
              <td class="tinglunon-status"><?php echo shaven_tinglunon_payment_badge($billPayment); ?></td>
              <td><?php echo h($billMonth); ?></td>
              <td><?php echo h($bill['period']); ?></td>
              <td><span class="shaven-due-date"><?php echo h($bill['due_label']); ?></span></td>
              <td class="tinglunon-money"><?php echo shaven_peso($bill['amount']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script src="<?php echo h(shaven_url('dist/js/shaven-tinglunon.js')); ?>"></script>
<?php
shaven_page_close();
