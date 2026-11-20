<?php
function shaven_owner_bill_amount($raw) {
    $text = trim((string)$raw);
    if ($text === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $text)) {
        return null;
    }
    $amount = round((float)$text, 2);
    if ($amount < 0.01 || $amount > 1000000) {
        return null;
    }
    return $amount;
}

function shaven_owner_bill_row($conn, $subscriberId, $billId) {
    $rows = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_uid, p.status, p.payment_amount,
                r.remit, r.batch_id, b.status AS batch_status
         FROM tbl_payments p
         LEFT JOIN tbl_remittances r ON r.paymentid = p.id
         LEFT JOIN tbl_remit_batches b ON b.id = r.batch_id
         WHERE p.id=? AND p.payment_uid=?
         LIMIT 1
         FOR UPDATE",
        'ii',
        [(int)$billId, (int)$subscriberId]
    );
    return $rows[0] ?? null;
}

function shaven_owner_refresh_batch($conn, $batchId) {
    $batchId = (int)$batchId;
    if ($batchId < 1) {
        return true;
    }
    $batches = shaven_rows(
        $conn,
        "SELECT id, status FROM tbl_remit_batches WHERE id=? LIMIT 1 FOR UPDATE",
        'i',
        [$batchId]
    );
    if (!$batches) {
        return true;
    }
    if ((string)($batches[0]['status'] ?? '') !== 'pending') {
        return false;
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
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_remit_batches SET amount=?, item_count=? WHERE id=? AND status='pending'",
        'dii',
        [round((float)($left[0]['amt'] ?? 0), 2), (int)($left[0]['c'] ?? 0), $batchId]
    );
    return $saved['ok'];
}

function shaven_owner_can_adjust_bills() {
    return shaven_role_is(1, 2);
}

function shaven_owner_update_bill_amount($conn, $subscriberId, $billId, $rawAmount) {
    if (!shaven_owner_can_adjust_bills()) {
        return ['ok' => false, 'message' => 'An admin or the owner can change a bill.'];
    }
    $amount = shaven_owner_bill_amount($rawAmount);
    if ($amount === null) {
        return ['ok' => false, 'message' => 'Enter a bill amount from 0.01 to 1000000.00.'];
    }
    $subscriberId = (int)$subscriberId;
    $billId = (int)$billId;
    if ($subscriberId < 1 || $billId < 1) {
        return ['ok' => false, 'message' => 'That bill was not found on this subscriber.'];
    }
    mysqli_begin_transaction($conn);
    $row = shaven_owner_bill_row($conn, $subscriberId, $billId);
    if (!$row) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'That bill was not found on this subscriber.'];
    }
    if ((int)$row['status'] === 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'A paid bill keeps its posted amount.'];
    }
    if (shaven_revert_is_remitted($row)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'This bill was already remitted, so the amount stays as posted.'];
    }
    if (round((float)$row['payment_amount'], 2) === $amount) {
        mysqli_rollback($conn);
        return ['ok' => true, 'message' => 'The bill amount is already ₱' . number_format($amount, 2) . '.'];
    }
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_payments SET payment_amount=? WHERE id=? AND payment_uid=? AND status=0",
        'dii',
        [$amount, $billId, $subscriberId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'The bill amount was not changed.'];
    }
    if (!shaven_owner_refresh_batch($conn, (int)($row['batch_id'] ?? 0))) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'The bill amount was not changed.'];
    }
    mysqli_commit($conn);
    return ['ok' => true, 'message' => 'The bill amount is now ₱' . number_format($amount, 2) . '.'];
}

function shaven_owner_delete_bill_locked($conn, $subscriberId, $billId) {
    $row = shaven_owner_bill_row($conn, $subscriberId, $billId);
    if (!$row) {
        return ['ok' => false, 'message' => 'That bill was not found on this subscriber.'];
    }
    if (shaven_revert_is_remitted($row)) {
        return ['ok' => false, 'message' => 'This bill was already remitted, so it stays on the account.'];
    }
    if (!shaven_revert_release($conn, $row)) {
        return ['ok' => false, 'message' => 'The bill was not deleted.'];
    }
    $saved = shaven_exec(
        $conn,
        "DELETE FROM tbl_payments WHERE id=? AND payment_uid=?",
        'ii',
        [(int)$billId, (int)$subscriberId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        return ['ok' => false, 'message' => 'The bill was not deleted.'];
    }
    return ['ok' => true, 'message' => 'The bill was deleted.'];
}

function shaven_owner_delete_bill($conn, $subscriberId, $billId) {
    if (!shaven_owner_can_adjust_bills()) {
        return ['ok' => false, 'message' => 'An admin or the owner can delete a bill.'];
    }
    $subscriberId = (int)$subscriberId;
    $billId = (int)$billId;
    if ($subscriberId < 1 || $billId < 1) {
        return ['ok' => false, 'message' => 'That bill was not found on this subscriber.'];
    }
    mysqli_begin_transaction($conn);
    $result = shaven_owner_delete_bill_locked($conn, $subscriberId, $billId);
    if (!$result['ok']) {
        mysqli_rollback($conn);
        return $result;
    }
    mysqli_commit($conn);
    return $result;
}

function shaven_owner_delete_bills($conn, $subscriberId, $rawIds) {
    if (!shaven_owner_can_adjust_bills()) {
        return ['ok' => false, 'message' => 'An admin or the owner can delete bills.', 'deleted' => 0];
    }
    $subscriberId = (int)$subscriberId;
    if ($subscriberId < 1) {
        return ['ok' => false, 'message' => 'That subscriber was not found.', 'deleted' => 0];
    }
    $ids = [];
    foreach ((array)$rawIds as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if (!$ids) {
        return ['ok' => false, 'message' => 'Select at least one bill to delete.', 'deleted' => 0];
    }
    if (count($ids) > 200) {
        return ['ok' => false, 'message' => 'Delete up to 200 bills at a time.', 'deleted' => 0];
    }
    mysqli_begin_transaction($conn);
    $deleted = 0;
    foreach ($ids as $billId) {
        $result = shaven_owner_delete_bill_locked($conn, $subscriberId, $billId);
        if (!$result['ok']) {
            mysqli_rollback($conn);
            return [
                'ok' => false,
                'message' => $result['message'] . ($deleted > 0 ? ' Nothing from this selection was deleted.' : ''),
                'deleted' => 0,
            ];
        }
        $deleted++;
    }
    mysqli_commit($conn);
    return [
        'ok' => true,
        'message' => $deleted === 1 ? '1 bill was deleted.' : $deleted . ' bills were deleted.',
        'deleted' => $deleted,
    ];
}
