<?php
function shaven_owner_batch_status($status) {
    $status = strtolower(trim((string)$status));
    if (in_array($status, ['held', 'pending', 'remitted'], true)) {
        return $status;
    }
    return '';
}

function shaven_owner_batch_status_label($status) {
    if ($status === 'pending') {
        return 'Pending';
    }
    if ($status === 'remitted') {
        return 'Remitted';
    }
    return 'Not submitted';
}

function shaven_owner_unremitted_where() {
    return "p.status=1 AND " . shaven_revert_not_remitted_sql();
}

function shaven_owner_unremitted_bills($conn) {
    if (shaven_role() !== 1) {
        return [];
    }
    return shaven_rows(
        $conn,
        "SELECT p.id, p.payment_amount, p.payment_date, p.pay_channel, p.postedby,
                p.payment_year, p.payment_month, p.payment_day, p.period_start, p.period_end, p.due_date,
                s.s_name, s.s_doi, s.account_no,
                u.sys_user_fullname AS poster,
                r.remit, r.batch_id, b.status AS batch_status, b.ref AS batch_ref
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
         LEFT JOIN tbl_sys_users u ON u.id = p.postedby
         LEFT JOIN tbl_remittances r ON r.paymentid = p.id
         LEFT JOIN tbl_remit_batches b ON b.id = r.batch_id
         WHERE " . shaven_owner_unremitted_where() . "
         ORDER BY s.s_name, p.payment_date, p.id"
    );
}

function shaven_owner_refresh_pending_batches($conn, array $batchIds) {
    $seen = [];
    foreach ($batchIds as $batchId) {
        $batchId = (int)$batchId;
        if ($batchId < 1 || isset($seen[$batchId])) {
            continue;
        }
        $seen[$batchId] = true;
        $batches = shaven_rows(
            $conn,
            "SELECT id, status FROM tbl_remit_batches WHERE id=? LIMIT 1 FOR UPDATE",
            'i',
            [$batchId]
        );
        if (!$batches || (string)($batches[0]['status'] ?? '') !== 'pending') {
            continue;
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
            if (!$removed['ok']) {
                return false;
            }
            continue;
        }
        $saved = shaven_exec(
            $conn,
            "UPDATE tbl_remit_batches SET amount=?, item_count=? WHERE id=? AND status='pending'",
            'dii',
            [round((float)($left[0]['amt'] ?? 0), 2), $count, $batchId]
        );
        if (!$saved['ok']) {
            return false;
        }
    }
    return true;
}

function shaven_owner_change_batch_status($conn, $userId, $rawIds, $status) {
    $userId = (int)$userId;
    if (shaven_role() !== 1 || shaven_remit_level($conn, $userId) !== 1) {
        return ['ok' => false, 'message' => 'Only the owner can change the batch status of unremitted bills.'];
    }
    $status = shaven_owner_batch_status($status);
    if ($status === '') {
        return ['ok' => false, 'message' => 'Choose Not submitted, Pending, or Remitted.'];
    }
    $ids = shaven_remit_id_list($rawIds);
    if (!$ids) {
        return ['ok' => false, 'message' => 'Mark the bills to update.'];
    }
    if (count($ids) > 500) {
        return ['ok' => false, 'message' => 'A batch status change can include up to 500 bills.'];
    }
    $in = implode(',', $ids);
    mysqli_begin_transaction($conn);
    $rows = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_amount, r.paymentid AS remit_id, r.batch_id, b.status AS batch_status
         FROM tbl_payments p
         LEFT JOIN tbl_remittances r ON r.paymentid = p.id
         LEFT JOIN tbl_remit_batches b ON b.id = r.batch_id
         WHERE p.id IN ({$in}) AND " . shaven_owner_unremitted_where() . "
         FOR UPDATE"
    );
    if (count($rows) !== count($ids)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'Those bills changed. Refresh the list and mark the ones that are still unremitted.'];
    }
    $sum = 0.0;
    $oldBatches = [];
    foreach ($rows as $row) {
        $sum += (float)$row['payment_amount'];
        if ((string)($row['batch_status'] ?? '') === 'pending' && (int)($row['batch_id'] ?? 0) > 0) {
            $oldBatches[] = (int)$row['batch_id'];
        }
    }
    $sum = round($sum, 2);
    $batchId = 0;
    $ref = '';
    if ($status === 'pending' || $status === 'remitted') {
        $ref = 'RB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        if ($status === 'pending') {
            $saved = shaven_exec(
                $conn,
                "INSERT INTO tbl_remit_batches (ref, collector_id, submitted_at, amount, item_count, status)
                 VALUES (?, ?, NOW(), ?, ?, 'pending')",
                'sidi',
                [$ref, $userId, $sum, count($rows)]
            );
        } else {
            $saved = shaven_exec(
                $conn,
                "INSERT INTO tbl_remit_batches (ref, collector_id, submitted_at, amount, item_count, status, confirmed_by, confirmed_at)
                 VALUES (?, ?, NOW(), ?, ?, 'confirmed', ?, NOW())",
                'sidii',
                [$ref, $userId, $sum, count($rows), $userId]
            );
        }
        if (!$saved['ok'] || (int)$saved['id'] < 1) {
            mysqli_rollback($conn);
            return ['ok' => false, 'message' => 'The batch status was not changed.'];
        }
        $batchId = (int)$saved['id'];
    }
    foreach ($rows as $row) {
        $paymentId = (int)$row['id'];
        $hasRemit = (int)($row['remit_id'] ?? 0) > 0;
        if ($status === 'held') {
            if ($hasRemit) {
                $saved = shaven_exec(
                    $conn,
                    "UPDATE tbl_remittances SET remit='0', batch_id=NULL WHERE paymentid=? AND (remit IS NULL OR remit<>'1')",
                    'i',
                    [$paymentId]
                );
            } else {
                $saved = shaven_exec(
                    $conn,
                    "INSERT INTO tbl_remittances (paymentid, dt, remit) VALUES (?, NOW(), '0')",
                    'i',
                    [$paymentId]
                );
            }
        } else {
            $remit = $status === 'remitted' ? '1' : '0';
            if ($hasRemit) {
                $saved = shaven_exec(
                    $conn,
                    "UPDATE tbl_remittances SET remit=?, batch_id=? WHERE paymentid=? AND (remit IS NULL OR remit<>'1')",
                    'sii',
                    [$remit, $batchId, $paymentId]
                );
            } else {
                $saved = shaven_exec(
                    $conn,
                    "INSERT INTO tbl_remittances (paymentid, dt, remit, batch_id) VALUES (?, NOW(), ?, ?)",
                    'isi',
                    [$paymentId, $remit, $batchId]
                );
            }
        }
        if (!$saved['ok']) {
            mysqli_rollback($conn);
            return ['ok' => false, 'message' => 'The batch status was not changed.'];
        }
    }
    if (!shaven_owner_refresh_pending_batches($conn, $oldBatches)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'message' => 'The batch status was not changed.'];
    }
    mysqli_commit($conn);
    $label = shaven_owner_batch_status_label($status);
    $count = count($rows);
    $noun = $count === 1 ? 'bill' : 'bills';
    $message = 'Updated ' . $count . ' ' . $noun . ' to ' . $label . '.';
    if ($ref !== '') {
        $message = 'Updated ' . $count . ' ' . $noun . ' to ' . $label . ' in batch ' . $ref . '.';
    }
    return ['ok' => true, 'message' => $message, 'ref' => $ref, 'count' => $count];
}
