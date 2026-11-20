<?php
function shaven_revert_is_remitted(array $row) {
    if ((string)($row['remit'] ?? '') === '1') {
        return true;
    }
    return (string)($row['batch_status'] ?? '') === 'confirmed';
}

function shaven_revert_hold_label(array $row) {
    if (shaven_revert_is_remitted($row)) {
        return 'Remitted';
    }
    $ref = trim((string)($row['batch_ref'] ?? ''));
    if ((string)($row['batch_status'] ?? '') === 'pending' && $ref !== '') {
        return 'In batch ' . $ref;
    }
    return 'Not remitted';
}

function shaven_revert_not_remitted_sql() {
    return "NOT EXISTS (
        SELECT 1 FROM tbl_remittances rx
        LEFT JOIN tbl_remit_batches bx ON bx.id = rx.batch_id
        WHERE rx.paymentid = p.id AND (rx.remit = 1 OR bx.status = 'confirmed')
    )";
}

function shaven_revert_select_sql() {
    return "SELECT p.id, p.status, p.payment_amount, p.payment_date, p.postedby, p.revert, p.revert_by, p.pay_channel,
                   p.payment_year, p.payment_month, p.payment_day, p.period_start, p.period_end, p.due_date,
                   s.s_name, s.s_doi, s.account_no,
                   u.sys_user_fullname AS poster,
                   rq.sys_user_fullname AS requester,
                   r.remit, r.batch_id, b.status AS batch_status, b.ref AS batch_ref
            FROM tbl_payments p
            INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
            LEFT JOIN tbl_sys_users u ON u.id = p.postedby
            LEFT JOIN tbl_sys_users rq ON rq.id = p.revert_by
            LEFT JOIN tbl_remittances r ON r.paymentid = p.id
            LEFT JOIN tbl_remit_batches b ON b.id = r.batch_id";
}

function shaven_revert_queue($conn, $userId) {
    $userId = (int)$userId;
    $level = shaven_remit_level($conn, $userId);
    if (!in_array($level, [1, 2, 3], true)) {
        return [];
    }
    $where = "p.status=1 AND (p.revert=1 OR " . shaven_revert_not_remitted_sql() . ")";
    $types = '';
    $params = [];
    if ($level === 3) {
        $where .= " AND p.postedby=?";
        $types = 'i';
        $params = [$userId];
    }
    return shaven_rows(
        $conn,
        shaven_revert_select_sql() . " WHERE {$where} ORDER BY p.revert DESC, s.s_name, p.payment_date DESC, p.id DESC",
        $types,
        $params
    );
}

function shaven_revert_approvals($conn, $userId) {
    $level = shaven_remit_level($conn, (int)$userId);
    if (!in_array($level, [1, 2], true)) {
        return [];
    }
    return shaven_rows(
        $conn,
        shaven_revert_select_sql() . " WHERE p.status=1 AND p.revert=1 ORDER BY p.payment_date DESC, p.id DESC"
    );
}

function shaven_revert_lock($conn, $paymentId) {
    $rows = shaven_rows(
        $conn,
        "SELECT p.id, p.status, p.revert, p.revert_by, p.postedby, r.remit, r.batch_id, b.status AS batch_status
         FROM tbl_payments p
         LEFT JOIN tbl_remittances r ON r.paymentid = p.id
         LEFT JOIN tbl_remit_batches b ON b.id = r.batch_id
         WHERE p.id=?
         LIMIT 1
         FOR UPDATE",
        'i',
        [(int)$paymentId]
    );
    return $rows[0] ?? null;
}

function shaven_revert_can_request(array $row, $userId, $level) {
    if (!in_array($level, [1, 2, 3], true)) {
        return false;
    }
    if ((int)$row['status'] !== 1 || (int)($row['revert'] ?? 0) === 1) {
        return false;
    }
    if (shaven_revert_is_remitted($row)) {
        return false;
    }
    if ($level === 3 && (int)($row['postedby'] ?? 0) !== (int)$userId) {
        return false;
    }
    return true;
}

function shaven_revert_can_cancel(array $row, $userId, $level) {
    if ((int)$row['status'] !== 1 || (int)($row['revert'] ?? 0) !== 1) {
        return false;
    }
    if (shaven_revert_is_remitted($row)) {
        return false;
    }
    if ($level === 3) {
        return (int)($row['postedby'] ?? 0) === (int)$userId;
    }
    return in_array($level, [1, 2], true);
}

function shaven_revert_can_approve(array $row, $userId, $level) {
    if (!in_array($level, [1, 2], true)) {
        return false;
    }
    if ((int)$row['status'] !== 1 || (int)($row['revert'] ?? 0) !== 1) {
        return false;
    }
    if (shaven_revert_is_remitted($row)) {
        return false;
    }
    $requester = (int)($row['revert_by'] ?? 0);
    if ($requester > 0) {
        return $requester !== (int)$userId;
    }
    return (int)($row['postedby'] ?? 0) !== (int)$userId;
}

function shaven_revert_request($conn, $userId, $paymentId) {
    $userId = (int)$userId;
    $paymentId = (int)$paymentId;
    $level = shaven_remit_level($conn, $userId);
    if (!in_array($level, [1, 2, 3], true)) {
        return ['ok' => false, 'error' => 'This account cannot ask for a revert.'];
    }
    if ($paymentId < 1) {
        return ['ok' => false, 'error' => 'That payment was not found.'];
    }
    mysqli_begin_transaction($conn);
    $row = shaven_revert_lock($conn, $paymentId);
    if (!$row || !shaven_revert_can_request($row, $userId, $level)) {
        mysqli_rollback($conn);
        if ($row && shaven_revert_is_remitted($row)) {
            return ['ok' => false, 'error' => 'This payment was already remitted, so it cannot be reverted.'];
        }
        if ($row && (int)($row['revert'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'This payment is already waiting for approval.'];
        }
        return ['ok' => false, 'error' => 'That paid bill cannot be reverted from this account.'];
    }
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_payments SET revert=1, revert_by=? WHERE id=? AND status=1 AND (revert IS NULL OR revert=0)",
        'ii',
        [$userId, $paymentId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The revert request was not saved.'];
    }
    mysqli_commit($conn);
    $fact = shaven_notify_payment_fact($conn, $paymentId);
    if ($fact) {
        shaven_notify_revert_requested($conn, $userId, $fact);
    }
    return ['ok' => true, 'error' => 'The revert is waiting for an admin or the owner. It stays available only while the payment has not been remitted.'];
}

function shaven_revert_cancel($conn, $userId, $paymentId) {
    $userId = (int)$userId;
    $paymentId = (int)$paymentId;
    $level = shaven_remit_level($conn, $userId);
    if ($paymentId < 1) {
        return ['ok' => false, 'error' => 'That payment was not found.'];
    }
    mysqli_begin_transaction($conn);
    $row = shaven_revert_lock($conn, $paymentId);
    if (!$row || !shaven_revert_can_cancel($row, $userId, $level)) {
        mysqli_rollback($conn);
        if ($row && shaven_revert_is_remitted($row)) {
            return ['ok' => false, 'error' => 'This payment was already remitted. An admin or the owner closes the request from Approve Revert.'];
        }
        return ['ok' => false, 'error' => 'That revert request cannot be cancelled from this account.'];
    }
    $fact = shaven_notify_payment_fact($conn, $paymentId);
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_payments SET revert=0, revert_by=NULL WHERE id=? AND status=1 AND revert=1",
        'i',
        [$paymentId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The revert request was not cancelled.'];
    }
    mysqli_commit($conn);
    if ($fact) {
        shaven_notify_revert_cancelled($conn, $userId, $fact);
    }
    return ['ok' => true, 'error' => 'The revert request was cancelled. The payment stays paid.'];
}

function shaven_revert_release($conn, array $row) {
    if (shaven_revert_is_remitted($row)) {
        return false;
    }
    $paymentId = (int)$row['id'];
    $batchId = (int)($row['batch_id'] ?? 0);
    if ($batchId > 0) {
        $batches = shaven_rows(
            $conn,
            "SELECT id, status FROM tbl_remit_batches WHERE id=? LIMIT 1 FOR UPDATE",
            'i',
            [$batchId]
        );
        if ($batches && (string)($batches[0]['status'] ?? '') === 'confirmed') {
            return false;
        }
    }
    $deleted = shaven_exec(
        $conn,
        "DELETE FROM tbl_remittances WHERE paymentid=? AND (remit IS NULL OR remit<>1)",
        'i',
        [$paymentId]
    );
    if (!$deleted['ok']) {
        return false;
    }
    if ($batchId < 1) {
        return true;
    }
    $left = shaven_rows(
        $conn,
        "SELECT COUNT(*) c, COALESCE(SUM(p.payment_amount), 0) amt
         FROM tbl_remittances r
         INNER JOIN tbl_payments p ON p.id = r.paymentid
         WHERE r.batch_id=?",
        'i',
        [$batchId]
    );
    $count = (int)($left[0]['c'] ?? 0);
    if ($count < 1) {
        $removed = shaven_exec(
            $conn,
            "DELETE FROM tbl_remit_batches WHERE id=? AND status='pending'",
            'i',
            [$batchId]
        );
        return $removed['ok'];
    }
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_remit_batches SET amount=?, item_count=? WHERE id=? AND status='pending'",
        'dii',
        [round((float)$left[0]['amt'], 2), $count, $batchId]
    );
    return $saved['ok'];
}

function shaven_revert_approve($conn, $userId, $paymentId) {
    $userId = (int)$userId;
    $paymentId = (int)$paymentId;
    $level = shaven_remit_level($conn, $userId);
    if (!in_array($level, [1, 2], true)) {
        return ['ok' => false, 'error' => 'An admin or the owner approves a revert.'];
    }
    if ($paymentId < 1) {
        return ['ok' => false, 'error' => 'That payment was not found.'];
    }
    mysqli_begin_transaction($conn);
    $row = shaven_revert_lock($conn, $paymentId);
    if (!$row) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'That payment was not found.'];
    }
    if (shaven_revert_is_remitted($row)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'This payment was already remitted, so it cannot be reverted.'];
    }
    if (!shaven_revert_can_approve($row, $userId, $level)) {
        mysqli_rollback($conn);
        $requester = (int)($row['revert_by'] ?? 0);
        if ($requester === $userId || ($requester < 1 && (int)($row['postedby'] ?? 0) === $userId)) {
            return ['ok' => false, 'error' => 'Another admin or the owner approves a revert you requested.'];
        }
        return ['ok' => false, 'error' => 'That revert is no longer waiting for approval.'];
    }
    if (!shaven_revert_release($conn, $row)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The payment could not be taken off the remittance list.'];
    }
    $fact = shaven_notify_payment_fact($conn, $paymentId);
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_payments
         SET status=0, revert=0, revert_by=NULL, payment_date=NULL, postedby=NULL, rf='', pay_channel='bill'
         WHERE id=? AND status=1 AND revert=1",
        'i',
        [$paymentId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The revert was not approved.'];
    }
    mysqli_commit($conn);
    if ($fact) {
        shaven_notify_revert_approved($conn, $userId, $fact);
    }
    return ['ok' => true, 'error' => 'The revert was approved. That bill is unpaid again.'];
}

function shaven_revert_close($conn, $userId, $paymentId) {
    $userId = (int)$userId;
    $paymentId = (int)$paymentId;
    $level = shaven_remit_level($conn, $userId);
    if (!in_array($level, [1, 2], true)) {
        return ['ok' => false, 'error' => 'An admin or the owner closes a revert request.'];
    }
    if ($paymentId < 1) {
        return ['ok' => false, 'error' => 'That payment was not found.'];
    }
    mysqli_begin_transaction($conn);
    $row = shaven_revert_lock($conn, $paymentId);
    if (!$row || (int)$row['status'] !== 1 || (int)($row['revert'] ?? 0) !== 1 || !shaven_revert_is_remitted($row)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'That request is not a remitted payment waiting to be closed.'];
    }
    $fact = shaven_notify_payment_fact($conn, $paymentId);
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_payments SET revert=0, revert_by=NULL WHERE id=? AND status=1 AND revert=1",
        'i',
        [$paymentId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The revert request was not closed.'];
    }
    mysqli_commit($conn);
    if ($fact) {
        shaven_notify_revert_closed($conn, $userId, $fact);
    }
    return ['ok' => true, 'error' => 'The revert request was closed. The payment stays paid because it was already remitted.'];
}

function shaven_revert_ids($rawIds) {
    $ids = [];
    foreach ((array)$rawIds as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function shaven_revert_request_many($conn, $userId, $rawIds) {
    $ids = shaven_revert_ids($rawIds);
    if (!$ids) {
        return ['ok' => false, 'error' => 'Select at least one payment to request.'];
    }
    if (count($ids) > 200) {
        return ['ok' => false, 'error' => 'Request up to 200 payments at a time.'];
    }
    $done = 0;
    $skipped = 0;
    foreach ($ids as $paymentId) {
        $result = shaven_revert_request($conn, $userId, $paymentId);
        if ($result['ok']) {
            $done++;
        } else {
            $skipped++;
        }
    }
    if ($done < 1) {
        return ['ok' => false, 'error' => 'None of the selected payments could be requested for revert.'];
    }
    $message = $done === 1
        ? '1 revert was requested and is waiting for approval.'
        : $done . ' reverts were requested and are waiting for approval.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' selection' . ($skipped === 1 ? ' was' : 's were') . ' skipped.';
    }
    return ['ok' => true, 'error' => $message];
}

function shaven_revert_cancel_many($conn, $userId, $rawIds) {
    $ids = shaven_revert_ids($rawIds);
    if (!$ids) {
        return ['ok' => false, 'error' => 'Select at least one request to cancel.'];
    }
    if (count($ids) > 200) {
        return ['ok' => false, 'error' => 'Cancel up to 200 requests at a time.'];
    }
    $done = 0;
    $skipped = 0;
    foreach ($ids as $paymentId) {
        $result = shaven_revert_cancel($conn, $userId, $paymentId);
        if ($result['ok']) {
            $done++;
        } else {
            $skipped++;
        }
    }
    if ($done < 1) {
        return ['ok' => false, 'error' => 'None of the selected revert requests could be cancelled.'];
    }
    $message = $done === 1
        ? '1 revert request was cancelled.'
        : $done . ' revert requests were cancelled.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' selection' . ($skipped === 1 ? ' was' : 's were') . ' skipped.';
    }
    return ['ok' => true, 'error' => $message];
}

function shaven_revert_approve_many($conn, $userId, $rawIds) {
    $ids = shaven_revert_ids($rawIds);
    if (!$ids) {
        return ['ok' => false, 'error' => 'Select at least one request to approve.'];
    }
    if (count($ids) > 200) {
        return ['ok' => false, 'error' => 'Approve up to 200 requests at a time.'];
    }
    $done = 0;
    $skipped = 0;
    foreach ($ids as $paymentId) {
        $result = shaven_revert_approve($conn, $userId, $paymentId);
        if ($result['ok']) {
            $done++;
        } else {
            $skipped++;
        }
    }
    if ($done < 1) {
        return ['ok' => false, 'error' => 'None of the selected revert requests could be approved.'];
    }
    $message = $done === 1
        ? '1 revert was approved. That bill is unpaid again.'
        : $done . ' reverts were approved. Those bills are unpaid again.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' selection' . ($skipped === 1 ? ' was' : 's were') . ' skipped.';
    }
    return ['ok' => true, 'error' => $message];
}

function shaven_revert_close_many($conn, $userId, $rawIds) {
    $ids = shaven_revert_ids($rawIds);
    if (!$ids) {
        return ['ok' => false, 'error' => 'Select at least one remitted request to close.'];
    }
    if (count($ids) > 200) {
        return ['ok' => false, 'error' => 'Close up to 200 requests at a time.'];
    }
    $done = 0;
    $skipped = 0;
    foreach ($ids as $paymentId) {
        $result = shaven_revert_close($conn, $userId, $paymentId);
        if ($result['ok']) {
            $done++;
        } else {
            $skipped++;
        }
    }
    if ($done < 1) {
        return ['ok' => false, 'error' => 'None of the selected remitted requests could be closed.'];
    }
    $message = $done === 1
        ? '1 remitted revert request was closed.'
        : $done . ' remitted revert requests were closed.';
    if ($skipped > 0) {
        $message .= ' ' . $skipped . ' selection' . ($skipped === 1 ? ' was' : 's were') . ' skipped.';
    }
    return ['ok' => true, 'error' => $message];
}

function shaven_revert_button($action, $paymentId, $label, $class, $confirm) {
    echo '<form method="post" class="mb-0" onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm), ENT_QUOTES, 'UTF-8') . ');">';
    echo '<input type="hidden" name="csrf" value="' . h(shaven_csrf_token()) . '">';
    echo '<input type="hidden" name="revert_action" value="' . h($action) . '">';
    echo '<input type="hidden" name="payment_id" value="' . (int)$paymentId . '">';
    echo '<button class="btn btn-sm ' . h($class) . '" type="submit">' . h($label) . '</button>';
    echo '</form>';
}

function shaven_revert_table(array $rows, $userId, $level, $mode) {
    $userId = (int)$userId;
    $level = (int)$level;
    $batch = ($mode === 'ask' || $mode === 'approve');
    $columns = $batch ? 10 : 9;
    $canRequestAny = false;
    $canCancelAny = false;
    $canApproveAny = false;
    $canCloseAny = false;
    if ($mode === 'ask') {
        foreach ($rows as $row) {
            if (shaven_revert_can_request($row, $userId, $level)) {
                $canRequestAny = true;
            }
            if (shaven_revert_can_cancel($row, $userId, $level)) {
                $canCancelAny = true;
            }
        }
    } elseif ($mode === 'approve') {
        foreach ($rows as $row) {
            if (shaven_revert_is_remitted($row)) {
                $canCloseAny = true;
            } elseif (shaven_revert_can_approve($row, $userId, $level)) {
                $canApproveAny = true;
            }
        }
    }
    $batchReady = ($mode === 'ask' && ($canRequestAny || $canCancelAny))
        || ($mode === 'approve' && ($canApproveAny || $canCloseAny));
    ?>
    <?php if ($batchReady): ?>
    <form method="post" id="revert-batch" class="d-none">
      <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
      <input type="hidden" name="revert_action" id="revert-batch-action" value="">
    </form>
    <div class="px-3 pt-3 d-flex flex-wrap align-items-center" style="gap:.5rem;">
      <?php if ($canRequestAny): ?>
        <button class="btn btn-sm btn-warning" type="submit" form="revert-batch" data-revert-batch="request" onclick="return window.shavenRevertBatch && window.shavenRevertBatch('request');">Request selected</button>
      <?php endif; ?>
      <?php if ($canCancelAny): ?>
        <button class="btn btn-sm btn-default" type="submit" form="revert-batch" data-revert-batch="cancel" onclick="return window.shavenRevertBatch && window.shavenRevertBatch('cancel');">Cancel selected</button>
      <?php endif; ?>
      <?php if ($canApproveAny): ?>
        <button class="btn btn-sm btn-warning" type="submit" form="revert-batch" data-revert-batch="approve" onclick="return window.shavenRevertBatch && window.shavenRevertBatch('approve');">Approve selected</button>
      <?php endif; ?>
      <?php if ($canCloseAny): ?>
        <button class="btn btn-sm btn-default" type="submit" form="revert-batch" data-revert-batch="close" onclick="return window.shavenRevertBatch && window.shavenRevertBatch('close');">Close selected</button>
      <?php endif; ?>
      <span class="text-muted small" id="revert-batch-count">0 selected</span>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <?php if ($batch): ?>
              <th style="width:42px;">
                <?php if ($batchReady): ?>
                  <input type="checkbox" id="revert-batch-all" aria-label="Select all visible payments">
                <?php endif; ?>
              </th>
            <?php endif; ?>
            <th>Account</th>
            <th>Subscriber</th>
            <th>Billing month</th>
            <th>Billing period</th>
            <th>Due date</th>
            <th>Posted</th>
            <th>Remittance</th>
            <th class="text-right">Amount</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="<?php echo (int)$columns; ?>"><?php echo $mode === 'approve' ? 'No revert request is waiting.' : 'No paid bill is waiting to be reverted.'; ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $due = shaven_due_text($row, $row['s_doi']);
            $month = shaven_billing_month_label($row);
            $period = shaven_bill_period_label($row, $row['s_doi']);
            $hold = shaven_revert_hold_label($row);
            $poster = trim((string)($row['poster'] ?? ''));
            if ($poster === '' && (string)($row['pay_channel'] ?? '') === 'portal') {
                $poster = 'Subscriber portal';
            }
            if ($poster === '') {
                $poster = 'Not recorded';
            }
            $requester = trim((string)($row['requester'] ?? ''));
            $find = strtolower(implode(' ', [
                $row['account_no'] ?? '',
                $row['s_name'] ?? '',
                $month,
                $period,
                $due,
                $poster,
                $requester,
                $hold,
            ]));
            $batchKind = '';
            if ($mode === 'ask') {
                if (shaven_revert_can_request($row, $userId, $level)) {
                    $batchKind = 'request';
                } elseif (shaven_revert_can_cancel($row, $userId, $level)) {
                    $batchKind = 'cancel';
                }
            } elseif ($mode === 'approve') {
                if (shaven_revert_is_remitted($row)) {
                    $batchKind = 'close';
                } elseif (shaven_revert_can_approve($row, $userId, $level)) {
                    $batchKind = 'approve';
                }
            }
          ?>
          <tr data-revert-row data-find="<?php echo h($find); ?>">
            <?php if ($batch): ?>
              <td>
                <?php if ($batchKind !== ''): ?>
                  <input class="revert-batch-check" type="checkbox" name="payment_ids[]" value="<?php echo (int)$row['id']; ?>" form="revert-batch" data-revert-kind="<?php echo h($batchKind); ?>" aria-label="Select payment">
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td><span class="shaven-account-no"><?php echo h(trim((string)($row['account_no'] ?? '')) !== '' ? $row['account_no'] : 'No account number'); ?></span></td>
            <td>
              <?php echo h($row['s_name']); ?>
              <?php if ((int)($row['revert'] ?? 0) === 1): ?>
                <div class="text-muted small">Requested<?php echo $requester !== '' ? ' by ' . h($requester) : ''; ?></div>
              <?php endif; ?>
            </td>
            <td><?php echo h($month); ?></td>
            <td><?php echo h($period); ?></td>
            <td><?php echo $due !== '' ? '<span class="shaven-due-date">' . h($due) . '</span>' : 'No due date'; ?></td>
            <td><?php echo h($poster); ?><div class="text-muted small"><?php echo h(shaven_date($row['payment_date'], 'M j, Y g:i A')); ?></div></td>
            <td><?php echo h($hold); ?></td>
            <td class="text-right"><?php echo shaven_peso($row['payment_amount']); ?></td>
            <td>
              <?php if ($mode === 'approve'): ?>
                <?php if (shaven_revert_is_remitted($row)): ?>
                  <?php shaven_revert_button('close', $row['id'], 'Close request', 'btn-default', 'Close this request? The payment stays paid because it was already remitted.'); ?>
                <?php elseif (shaven_revert_can_approve($row, $userId, $level)): ?>
                  <?php shaven_revert_button('approve', $row['id'], 'Approve revert', 'btn-warning', 'Approve this revert? The bill becomes unpaid again.'); ?>
                <?php else: ?>
                  <span class="text-muted">Waiting for another admin or the owner</span>
                <?php endif; ?>
              <?php elseif ((int)($row['revert'] ?? 0) === 1 && shaven_revert_is_remitted($row)): ?>
                <span class="badge badge-secondary">Remitted</span>
              <?php elseif (shaven_revert_can_cancel($row, $userId, $level)): ?>
                <?php shaven_revert_button('cancel', $row['id'], 'Cancel request', 'btn-default', 'Cancel this revert request? The payment stays paid.'); ?>
              <?php elseif (shaven_revert_can_request($row, $userId, $level)): ?>
                <?php shaven_revert_button('request', $row['id'], 'Request revert', 'btn-warning', 'Ask an admin or the owner to revert this payment?'); ?>
              <?php else: ?>
                <span class="badge badge-warning">Waiting for approval</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($batchReady): ?>
    <script>
    (function () {
      if (window.shavenRevertBatchReady) return;
      window.shavenRevertBatchReady = true;
      var all = document.getElementById('revert-batch-all');
      var actionInput = document.getElementById('revert-batch-action');
      var countNode = document.getElementById('revert-batch-count');
      function visibleChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-revert-row]:not(.d-none) .revert-batch-check'));
      }
      function syncCount() {
        if (!countNode) return;
        var selected = document.querySelectorAll('.revert-batch-check:checked').length;
        countNode.textContent = selected + ' selected';
        if (all) {
          var boxes = visibleChecks();
          all.checked = boxes.length > 0 && boxes.every(function (box) { return box.checked; });
          all.indeterminate = !all.checked && boxes.some(function (box) { return box.checked; });
        }
      }
      document.querySelectorAll('.revert-batch-check').forEach(function (box) {
        box.addEventListener('change', syncCount);
      });
      if (all) {
        all.addEventListener('change', function () {
          visibleChecks().forEach(function (box) { box.checked = all.checked; });
          syncCount();
        });
      }
      window.shavenRevertBatch = function (kind) {
        var selected = Array.prototype.slice.call(document.querySelectorAll('.revert-batch-check:checked'));
        var matched = selected.filter(function (box) {
          return (box.getAttribute('data-revert-kind') || '') === kind;
        });
        var emptyMessages = {
          request: 'Select at least one payment to request.',
          cancel: 'Select at least one waiting request to cancel.',
          approve: 'Select at least one request to approve.',
          close: 'Select at least one remitted request to close.'
        };
        if (!matched.length) {
          window.alert(emptyMessages[kind] || 'Select at least one payment.');
          return false;
        }
        selected.forEach(function (box) {
          box.disabled = (box.getAttribute('data-revert-kind') || '') !== kind;
        });
        if (actionInput) actionInput.value = kind + '_batch';
        var confirmMessages = {
          request: 'Request revert for ' + matched.length + ' payment' + (matched.length === 1 ? '' : 's') + '? An admin or the owner must approve.',
          cancel: 'Cancel ' + matched.length + ' revert request' + (matched.length === 1 ? '' : 's') + '? Those payments stay paid.',
          approve: 'Approve ' + matched.length + ' revert' + (matched.length === 1 ? '' : 's') + '? Those bills become unpaid again.',
          close: 'Close ' + matched.length + ' remitted request' + (matched.length === 1 ? '' : 's') + '? Those payments stay paid.'
        };
        if (!window.confirm(confirmMessages[kind] || 'Continue with the selected payments?')) {
          selected.forEach(function (box) { box.disabled = false; });
          return false;
        }
        return true;
      };
      syncCount();
    })();
    </script>
    <?php endif; ?>
    <?php
}
