<?php
function shaven_render_payment_desk(array $payDesk) {
    ?>
    <?php shaven_flash_html(); ?>
    <div class="card card-primary" id="pay-desk">
      <div class="card-header">
        <h3 class="card-title">Pay a subscriber</h3>
      </div>
      <div class="card-body">
        <p class="mb-3">Post the selected bills from the oldest due date, or enter an advance amount. The amount is applied to the oldest open bill first. Any amount above that month’s bill is forwarded to the next month, and a remainder smaller than the following bill stays open on that month. The cash stays on your account until you submit those payments together as a remittance batch. An admin confirms the batch.</p>
        <form method="get" action="collections.php#pay-desk" class="form-row align-items-end mb-3">
          <div class="form-group col-md-8">
            <label for="pay-subscriber">Subscriber</label>
            <select class="form-control" id="pay-subscriber" name="uid">
              <option value="">Choose a subscriber</option>
              <?php foreach ($payDesk['subscribers'] as $subscriber): ?>
                <option value="<?php echo (int)$subscriber['id']; ?>" <?php echo ((int)$subscriber['id'] === (int)$payDesk['requested_id']) ? 'selected' : ''; ?>>
                  <?php echo h($subscriber['s_name']); ?>
                  <?php echo ((int)$subscriber['open_count'] === 0) ? ' — no open bill' : ' — ' . (int)$subscriber['open_count'] . ' open'; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <button class="btn btn-default" type="submit">Open account</button>
          </div>
        </form>
        <?php if ((int)$payDesk['requested_id'] > 0 && !$payDesk['profile']): ?>
          <div class="alert alert-warning mb-0">That subscriber is not an active account.</div>
        <?php elseif ($payDesk['profile']): ?>
          <?php
            $deskProfile = $payDesk['profile'];
            $deskBills = [];
            foreach ($payDesk['open'] as $bill) {
                $deskBills[] = [
                    'label' => shaven_bill_period_label($bill, $deskProfile['s_doi']),
                    'amount' => round((float)$bill['payment_amount'], 2),
                ];
            }
          ?>
          <p class="mb-2">
            <strong><?php echo h($deskProfile['s_name']); ?></strong>
            · <?php echo h($deskProfile['p_name'] ?: 'No plan'); ?>
            · <?php echo shaven_peso($deskProfile['p_monthly_amount']); ?> each month
            · Installed <?php echo h(shaven_date($deskProfile['s_doi'], 'F j, Y')); ?>
          </p>
          <form method="post" id="pay-desk-form" data-monthly="<?php echo h((string)round((float)$deskProfile['p_monthly_amount'], 2)); ?>" data-open="<?php echo h(json_encode($deskBills, JSON_UNESCAPED_UNICODE)); ?>">
            <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
            <input type="hidden" name="pay_desk" value="1">
            <input type="hidden" name="uid" value="<?php echo (int)$deskProfile['id']; ?>">
            <?php if ($payDesk['open']): ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-3">
                  <thead>
                    <tr>
                      <th>Pay</th>
                      <th>Billing period</th>
                      <th>Due date</th>
                      <th>Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($payDesk['open'] as $index => $bill): ?>
                      <tr>
                        <td>
                          <input class="shaven-pay-bill" type="checkbox" name="bill_ids[]" value="<?php echo (int)$bill['id']; ?>" data-amount="<?php echo h((string)$bill['payment_amount']); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                        </td>
                        <td><?php echo h(shaven_bill_period_label($bill, $deskProfile['s_doi'])); ?></td>
                        <td><?php echo h(shaven_due_text($bill, $deskProfile['s_doi']) ?: 'No due date'); ?></td>
                        <td><?php echo shaven_peso($bill['payment_amount']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-success">This account has no open bill. An advance payment can cover the next months.</div>
            <?php endif; ?>
            <div class="form-group" style="max-width: 16rem;">
              <label for="pay-medium">Payment medium</label>
              <select class="form-control" id="pay-medium" name="pay_medium">
                <option value="cash" selected>Cash</option>
                <option value="gcash">GCash</option>
              </select>
            </div>
            <div class="form-row align-items-end">
              <div class="form-group col-md-4">
                <label for="advance-amount">Advance amount</label>
                <input class="form-control" id="advance-amount" type="number" name="advance_amount" min="0" step="0.01" placeholder="0.00">
              </div>
              <div class="form-group col-md-4">
                <label for="advance-months">Advance months</label>
                <input class="form-control" id="advance-months" type="number" name="advance_months" min="0" max="12" value="0">
              </div>
              <div class="form-group col-md-4">
                <p class="mb-1">Selected total: <strong>&#8369;<span id="pay-desk-total">0.00</span></strong></p>
                <p class="text-muted mb-0" id="advance-note">Advance months stay off until every open bill is included.</p>
              </div>
            </div>
            <ul class="list-unstyled mb-3" id="amount-preview" hidden></ul>
            <?php if ($payDesk['advances']): ?>
              <ul class="list-unstyled mb-3" id="advance-preview">
                <?php foreach ($payDesk['advances'] as $index => $period): ?>
                  <?php $periodLabel = shaven_date($period['start'], 'F j, Y') . ' through ' . shaven_date($period['end'], 'F j, Y'); ?>
                  <li class="shaven-advance-line" data-n="<?php echo $index + 1; ?>" data-label="<?php echo h($periodLabel); ?>" hidden>
                    Advance <?php echo $index + 1; ?>: <?php echo h($periodLabel); ?>, due <?php echo h(shaven_date($period['due'], 'F j, Y')); ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Post payment</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
}
