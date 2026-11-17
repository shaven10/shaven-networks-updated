<?php
function shaven_render_account_stats(array $account) {
    $profile = $account['profile'];
    ?>
    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
          <div class="inner"><h3><?php echo shaven_peso($account['balance']); ?></h3><p>Open balance</p></div>
          <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
          <span class="small-box-footer"><?php echo (int)$account['open_count']; ?> open bill<?php echo (int)$account['open_count'] === 1 ? '' : 's'; ?></span>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
          <div class="inner">
            <h3><?php echo h($profile['p_name'] ?: 'No plan'); ?></h3>
            <p><?php echo h(shaven_speed_label($profile['p_speed_kb'] ?? 0)); ?> · <?php echo shaven_peso($profile['p_monthly_amount'] ?? 0); ?> / month</p>
          </div>
          <div class="icon"><i class="fas fa-wifi"></i></div>
          <span class="small-box-footer"><?php echo h($profile['medium_name'] ?: 'Medium on file'); ?></span>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box <?php echo (int)$profile['status'] === 1 ? 'bg-success' : 'bg-warning'; ?>">
          <div class="inner"><h3><?php echo (int)$profile['status'] === 1 ? 'Active' : ((int)$profile['status'] === 2 ? 'Inactive' : 'Pending'); ?></h3><p>Line status</p></div>
          <div class="icon"><i class="fas fa-signal"></i></div>
          <span class="small-box-footer">Installed <?php echo h(shaven_date($profile['s_doi'])); ?></span>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
          <div class="inner"><h3><?php echo shaven_peso($account['year_paid']); ?></h3><p>Paid in <?php echo (int)$account['year']; ?></p></div>
          <div class="icon"><i class="fas fa-receipt"></i></div>
          <span class="small-box-footer"><?php echo (int)$account['year_paid_count']; ?> posted payment<?php echo (int)$account['year_paid_count'] === 1 ? '' : 's'; ?></span>
        </div>
      </div>
    </div>
    <?php
}

function shaven_render_account_profile(array $account) {
    $profile = $account['profile'];
    ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Service details</h3></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <dl class="row mb-0">
              <dt class="col-sm-4">Account</dt><dd class="col-sm-8"><?php echo h($profile['account_no'] ?? ''); ?></dd>
              <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?php echo h($profile['s_name']); ?></dd>
              <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?php echo nl2br(h($profile['s_address'])); ?></dd>
              <dt class="col-sm-4">Installed</dt><dd class="col-sm-8"><?php echo h(shaven_date($profile['s_doi'], 'F j, Y')); ?></dd>
            </dl>
          </div>
          <div class="col-md-6">
            <dl class="row mb-0">
              <dt class="col-sm-4">Plan</dt><dd class="col-sm-8"><?php echo h($profile['p_name'] ?: 'No plan'); ?> (<?php echo h(shaven_speed_label($profile['p_speed_kb'] ?? 0)); ?>)</dd>
              <dt class="col-sm-4">Monthly rate</dt><dd class="col-sm-8"><?php echo shaven_peso($profile['p_monthly_amount'] ?? 0); ?></dd>
              <dt class="col-sm-4">Medium</dt><dd class="col-sm-8"><?php echo h($profile['medium_name'] ?: '—'); ?></dd>
              <dt class="col-sm-4">On record since</dt><dd class="col-sm-8"><?php echo h(shaven_date($profile['timestamp'], 'M j, Y g:i A')); ?></dd>
              <dt class="col-sm-4">Lifetime paid</dt><dd class="col-sm-8"><?php echo shaven_peso($account['lifetime_paid']); ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
    <?php
}

function shaven_render_owner_bill_tools($subscriberId, array $bill) {
    $subscriberId = (int)$subscriberId;
    $billId = (int)$bill['id'];
    $amount = number_format((float)$bill['payment_amount'], 2, '.', '');
    $paid = (int)($bill['status'] ?? 0) === 1;
    ?>
    <div class="shaven-bill-tools">
      <?php if (!$paid): ?>
      <form method="post" class="shaven-bill-amount" action="<?php echo h(shaven_url('pages/tables/subscriber_view.php?uid=' . $subscriberId)); ?>">
        <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
        <input type="hidden" name="uid" value="<?php echo $subscriberId; ?>">
        <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
        <input type="hidden" name="action" value="update_amount">
        <input class="form-control form-control-sm" type="number" name="amount" min="0.01" max="1000000" step="0.01" value="<?php echo h($amount); ?>" required aria-label="Bill amount">
        <button class="btn btn-sm btn-default" type="submit">Update amount</button>
      </form>
      <?php endif; ?>
      <form method="post" action="<?php echo h(shaven_url('pages/tables/subscriber_view.php?uid=' . $subscriberId)); ?>" onsubmit="return confirm('Delete this bill?');">
        <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
        <input type="hidden" name="uid" value="<?php echo $subscriberId; ?>">
        <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
        <input type="hidden" name="action" value="delete_bill">
        <button class="btn btn-sm btn-danger" type="submit">Delete bill</button>
      </form>
    </div>
    <?php
}

function shaven_render_open_bills(array $account, $withChecks = false, $ownerTools = false) {
    $columns = ($withChecks ? 6 : 5) + ($ownerTools ? 1 : 0);
    ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Open bills</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <?php if ($withChecks): ?><th style="width:42px;"></th><?php endif; ?>
              <th>Billing period</th>
              <th>Due date</th>
              <th>Amount</th>
              <th>Payment status</th>
              <th>Billed</th>
              <?php if ($ownerTools): ?><th>Options</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!$account['open_bills']): ?>
            <tr><td colspan="<?php echo (int)$columns; ?>">This account has no open bill.</td></tr>
          <?php endif; ?>
          <?php foreach ($account['open_bills'] as $bill): ?>
            <tr>
              <?php if ($withChecks): ?>
                <td><input class="bill-check" type="checkbox" name="bill_ids[]" value="<?php echo (int)$bill['id']; ?>" data-amount="<?php echo h($bill['payment_amount']); ?>" checked></td>
              <?php endif; ?>
              <?php $openPeriod = shaven_bill_period($bill, $account['profile']['s_doi'] ?? ''); ?>
              <td><?php echo h($openPeriod ? shaven_bill_period_label($bill, $account['profile']['s_doi'] ?? '') : ($bill['payment_month'] . ' ' . $bill['payment_year'])); ?></td>
              <td><?php echo !empty($openPeriod['due']) ? '<span class="shaven-due-date">' . h(shaven_date($openPeriod['due'], 'F j, Y')) . '</span>' : h((string)(int)$bill['payment_day']); ?></td>
              <td><?php echo shaven_peso($bill['payment_amount']); ?></td>
              <td><?php echo shaven_payment_status_badge($bill['status'] ?? 0); ?></td>
              <td><?php echo h(shaven_date($bill['p_timestamp'])); ?></td>
              <?php if ($ownerTools): ?>
                <td><?php shaven_render_owner_bill_tools((int)$account['profile']['id'], $bill); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}

function shaven_render_payment_history(array $account, $ownerTools = false) {
    $installed = $account['profile']['s_doi'] ?? '';
    $paidTotal = 0.0;
    foreach ($account['history'] as $row) {
        $paidTotal += (float)$row['payment_amount'];
    }
    $paidCount = count($account['history']);
    ?>
    <div class="shaven-history-fit">
    <div class="card shaven-history">
      <div class="card-header">
        <h3 class="card-title">Payment history</h3>
        <div class="card-tools"><span class="badge badge-success"><?php echo (int)$paidCount; ?> paid · <?php echo shaven_peso($paidTotal); ?></span></div>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Paid on</th>
              <th>Billing month</th>
              <th>Due date</th>
              <th>Amount</th>
              <th>Payment status</th>
              <th>Channel</th>
              <th>Posted by</th>
              <th>Receipt</th>
              <?php if ($ownerTools): ?><th>Options</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php if (!$account['history']): ?>
            <tr><td colspan="<?php echo $ownerTools ? 9 : 8; ?>">No posted payment is on file yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($account['history'] as $row): ?>
            <?php $period = shaven_bill_period($row, $installed); ?>
            <tr>
              <td><?php echo !empty($row['payment_date']) ? h(shaven_date($row['payment_date'], 'F j, Y g:i A')) : '—'; ?></td>
              <td><?php echo h(trim((string)$row['payment_month'] . ' ' . (string)$row['payment_year'])); ?></td>
              <td>
                <?php if (!empty($period['due'])): ?>
                  <span class="shaven-due-date"><?php echo h(shaven_date($period['due'], 'F j, Y')); ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
                <span class="shaven-history-inclusive"><?php echo h(shaven_bill_period_label($row, $installed)); ?></span>
              </td>
              <td><?php echo shaven_peso($row['payment_amount']); ?></td>
              <td><?php echo shaven_payment_status_badge($row['status'] ?? 1); ?></td>
              <td><?php echo h(shaven_channel_label(1, $row['pay_channel'])); ?></td>
              <td><?php echo h($row['sys_user_fullname'] ?: '—'); ?></td>
              <td>
                <?php if (shaven_role_is(1, 2, 4)): ?>
                  <a href="<?php echo h(shaven_url('pages/tables/receipt.php?id=' . (int)$row['id'])); ?>">Receipt</a>
                <?php else: ?>
                  <?php echo h($row['rf'] ?: '—'); ?>
                <?php endif; ?>
              </td>
              <?php if ($ownerTools): ?>
                <td><?php shaven_render_owner_bill_tools((int)$account['profile']['id'], $row); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    </div>
    <?php
}

function shaven_render_plan_changes(array $account) {
    ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Plan changes</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>When</th><th>From</th><th>To</th><th>By</th><th>Note</th></tr></thead>
          <tbody>
          <?php if (!$account['plan_changes']): ?>
            <tr><td colspan="5">No plan change is recorded yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($account['plan_changes'] as $row): ?>
            <tr>
              <td><?php echo h(shaven_date($row['changed_at'], 'M j, Y g:i A')); ?></td>
              <td><?php echo h($row['old_name'] ?: 'Previous plan'); ?></td>
              <td><?php echo h(($row['new_name'] ?: 'New plan') . ' · ' . number_format((float)$row['new_amount'], 2)); ?></td>
              <td><?php echo h($row['sys_user_fullname'] ?: '—'); ?></td>
              <td><?php echo h($row['note']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}
