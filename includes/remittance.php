<?php
function shaven_remit_level($conn, $userId) {
    $rows = shaven_rows(
        $conn,
        "SELECT sys_level FROM tbl_sys_users WHERE id=? AND status=1 LIMIT 1",
        'i',
        [(int)$userId]
    );
    return $rows ? (int)$rows[0]['sys_level'] : 0;
}

function shaven_remit_id_list($raw) {
    $ids = [];
    foreach ((array)$raw as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function shaven_remit_payment_sql() {
    return "SELECT p.id, p.payment_amount, p.payment_date, p.rf, p.payment_year, p.payment_month, p.payment_day,
                   p.period_start, p.period_end, p.due_date, s.s_name, s.s_doi, r.batch_id
            FROM tbl_payments p
            INNER JOIN tbl_remittances r ON r.paymentid = p.id
            INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid";
}

function shaven_remit_held($conn, $userId, $limit = 200) {
    $userId = (int)$userId;
    $limit = max(1, min(200, (int)$limit));
    $countRows = shaven_rows(
        $conn,
        "SELECT COUNT(*) c, COALESCE(SUM(p.payment_amount), 0) amt
         FROM tbl_payments p
         INNER JOIN tbl_remittances r ON r.paymentid = p.id
         WHERE p.postedby=? AND p.status=1 AND r.remit='0' AND r.batch_id IS NULL",
        'i',
        [$userId]
    );
    $rows = shaven_rows(
        $conn,
        shaven_remit_payment_sql() . "
         WHERE p.postedby=? AND p.status=1 AND r.remit='0' AND r.batch_id IS NULL
         ORDER BY p.payment_date, p.id
         LIMIT " . $limit,
        'i',
        [$userId]
    );
    return [
        'count' => (int)($countRows[0]['c'] ?? 0),
        'amount' => (float)($countRows[0]['amt'] ?? 0),
        'rows' => $rows,
    ];
}

function shaven_remit_attach_items($conn, array $batches) {
    if (!$batches) {
        return [];
    }
    $ids = [];
    foreach ($batches as $batch) {
        $id = (int)$batch['id'];
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (!$ids) {
        return $batches;
    }
    $items = shaven_rows(
        $conn,
        shaven_remit_payment_sql() . " WHERE r.batch_id IN (" . implode(',', $ids) . ") ORDER BY s.s_name, p.payment_date, p.id"
    );
    $grouped = [];
    foreach ($items as $item) {
        $grouped[(int)$item['batch_id']][] = $item;
    }
    foreach ($batches as $index => $batch) {
        $rows = $grouped[(int)$batch['id']] ?? [];
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float)$row['payment_amount'];
        }
        $batches[$index]['items'] = $rows;
        $batches[$index]['live_amount'] = $sum;
        $batches[$index]['live_count'] = count($rows);
    }
    return $batches;
}

function shaven_remit_batches_for($conn, $userId) {
    $rows = shaven_rows(
        $conn,
        "SELECT b.id, b.ref, b.collector_id, b.submitted_at, b.amount, b.item_count, b.status, b.confirmed_at,
                u.sys_user_fullname AS collector, a.sys_user_fullname AS confirmer
         FROM tbl_remit_batches b
         INNER JOIN tbl_sys_users u ON u.id = b.collector_id
         LEFT JOIN tbl_sys_users a ON a.id = b.confirmed_by
         WHERE b.collector_id=?
         ORDER BY b.submitted_at DESC
         LIMIT 15",
        'i',
        [(int)$userId]
    );
    return shaven_remit_attach_items($conn, $rows);
}

function shaven_remit_pending($conn, $confirmerId) {
    $rows = shaven_rows(
        $conn,
        "SELECT b.id, b.ref, b.collector_id, b.submitted_at, b.amount, b.item_count, b.status,
                u.sys_user_fullname AS collector
         FROM tbl_remit_batches b
         INNER JOIN tbl_sys_users u ON u.id = b.collector_id
         WHERE b.status='pending' AND b.collector_id<>?
         ORDER BY b.submitted_at, b.id",
        'i',
        [(int)$confirmerId]
    );
    return shaven_remit_attach_items($conn, $rows);
}

function shaven_remit_field_holding($conn) {
    return shaven_rows(
        $conn,
        "SELECT u.sys_user_fullname, COUNT(*) c, COALESCE(SUM(p.payment_amount), 0) amt
         FROM tbl_remittances r
         INNER JOIN tbl_payments p ON p.id = r.paymentid
         INNER JOIN tbl_sys_users u ON u.id = p.postedby
         WHERE p.status=1 AND r.remit='0' AND r.batch_id IS NULL AND u.sys_level=3
         GROUP BY u.id, u.sys_user_fullname
         ORDER BY u.sys_user_fullname"
    );
}

function shaven_remit_confirmed_tech($conn, $limit = 30) {
    $limit = max(1, min(100, (int)$limit));
    $rows = shaven_rows(
        $conn,
        "SELECT b.id, b.ref, b.collector_id, b.submitted_at, b.amount, b.item_count, b.status, b.confirmed_at,
                u.sys_user_fullname AS collector, a.sys_user_fullname AS confirmer
         FROM tbl_remit_batches b
         INNER JOIN tbl_sys_users u ON u.id = b.collector_id
         LEFT JOIN tbl_sys_users a ON a.id = b.confirmed_by
         WHERE b.status='confirmed' AND u.sys_level=3
         ORDER BY b.confirmed_at DESC, b.id DESC
         LIMIT " . $limit
    );
    return shaven_remit_attach_items($conn, $rows);
}

function shaven_remit_waiting($conn, $userId) {
    $rows = shaven_rows(
        $conn,
        "SELECT COUNT(*) c, COALESCE(SUM(p.payment_amount), 0) amt
         FROM tbl_payments p
         INNER JOIN tbl_remittances r ON r.paymentid = p.id
         INNER JOIN tbl_remit_batches b ON b.id = r.batch_id
         WHERE p.postedby=? AND p.status=1 AND r.remit='0' AND b.status='pending'",
        'i',
        [(int)$userId]
    );
    return [
        'count' => (int)($rows[0]['c'] ?? 0),
        'amount' => (float)($rows[0]['amt'] ?? 0),
    ];
}

function shaven_remit_confirm_totals($conn, $userId) {
    $rows = shaven_rows(
        $conn,
        "SELECT COUNT(DISTINCT b.id) batches, COUNT(p.id) payments, COALESCE(SUM(p.payment_amount), 0) amt
         FROM tbl_remit_batches b
         LEFT JOIN tbl_remittances r ON r.batch_id = b.id AND r.remit='0'
         LEFT JOIN tbl_payments p ON p.id = r.paymentid AND p.status=1
         WHERE b.status='pending' AND b.collector_id<>?",
        'i',
        [(int)$userId]
    );
    return [
        'batches' => (int)($rows[0]['batches'] ?? 0),
        'payments' => (int)($rows[0]['payments'] ?? 0),
        'amount' => (float)($rows[0]['amt'] ?? 0),
    ];
}

function shaven_remit_submit_batch($conn, $userId, $rawIds) {
    $userId = (int)$userId;
    $level = shaven_remit_level($conn, $userId);
    if (!in_array($level, [1, 2, 3], true)) {
        return ['ok' => false, 'error' => 'This account cannot submit a remittance.'];
    }
    $ids = shaven_remit_id_list($rawIds);
    if (!$ids) {
        return ['ok' => false, 'error' => 'Select the collections to include in this batch.'];
    }
    if (count($ids) > 200) {
        return ['ok' => false, 'error' => 'A batch can include up to 200 payments. Submit the rest in the next batch.'];
    }
    $in = implode(',', $ids);
    mysqli_begin_transaction($conn);
    $rows = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_amount
         FROM tbl_payments p
         INNER JOIN tbl_remittances r ON r.paymentid = p.id
         WHERE p.id IN ({$in}) AND p.postedby=? AND p.status=1 AND r.remit='0' AND r.batch_id IS NULL
         FOR UPDATE",
        'i',
        [$userId]
    );
    if (count($rows) !== count($ids)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'Those collections changed. Refresh the list and submit the ones still on your account.'];
    }
    $sum = 0.0;
    foreach ($rows as $row) {
        $sum += (float)$row['payment_amount'];
    }
    $ref = 'RB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $saved = shaven_exec(
        $conn,
        "INSERT INTO tbl_remit_batches (ref, collector_id, submitted_at, amount, item_count, status)
         VALUES (?, ?, NOW(), ?, ?, 'pending')",
        'sidi',
        [$ref, $userId, round($sum, 2), count($rows)]
    );
    if (!$saved['ok'] || (int)$saved['id'] < 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The remittance batch was not saved.'];
    }
    $batchId = (int)$saved['id'];
    $updated = shaven_exec(
        $conn,
        "UPDATE tbl_remittances r
         INNER JOIN tbl_payments p ON p.id = r.paymentid
         SET r.batch_id=?
         WHERE p.id IN ({$in}) AND p.postedby=? AND p.status=1 AND r.remit='0' AND r.batch_id IS NULL",
        'ii',
        [$batchId, $userId]
    );
    if (!$updated['ok'] || (int)$updated['affected'] !== count($ids)) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The remittance batch was not saved.'];
    }
    mysqli_commit($conn);
    shaven_notify_remit_submitted($conn, $userId, $ref, count($ids), round($sum, 2));
    return [
        'ok' => true,
        'error' => '',
        'ref' => $ref,
        'count' => count($ids),
        'amount' => round($sum, 2),
    ];
}

function shaven_remit_confirm_batch($conn, $userId, $batchId) {
    $userId = (int)$userId;
    $batchId = (int)$batchId;
    $level = shaven_remit_level($conn, $userId);
    if (!in_array($level, [1, 2], true)) {
        return ['ok' => false, 'error' => 'An admin confirms a remittance batch.'];
    }
    if ($batchId < 1) {
        return ['ok' => false, 'error' => 'That remittance batch was not found.'];
    }
    mysqli_begin_transaction($conn);
    $batches = shaven_rows(
        $conn,
        "SELECT id, ref, collector_id, status FROM tbl_remit_batches WHERE id=? LIMIT 1 FOR UPDATE",
        'i',
        [$batchId]
    );
    if (!$batches || ($batches[0]['status'] ?? '') !== 'pending') {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'That remittance batch is no longer waiting for confirmation.'];
    }
    if ((int)$batches[0]['collector_id'] === $userId) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'Another admin confirms a batch you submitted.'];
    }
    $items = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_amount
         FROM tbl_remittances r
         INNER JOIN tbl_payments p ON p.id = r.paymentid
         WHERE r.batch_id=? AND r.remit='0' AND p.status=1
         FOR UPDATE",
        'i',
        [$batchId]
    );
    if (!$items) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'That batch has no collections left to confirm.'];
    }
    $sum = 0.0;
    foreach ($items as $item) {
        $sum += (float)$item['payment_amount'];
    }
    $marked = shaven_exec(
        $conn,
        "UPDATE tbl_remittances SET remit='1' WHERE batch_id=? AND remit='0'",
        'i',
        [$batchId]
    );
    if (!$marked['ok'] || (int)$marked['affected'] < 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The remittance was not confirmed.'];
    }
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_remit_batches
         SET status='confirmed', confirmed_by=?, confirmed_at=NOW(), amount=?, item_count=?
         WHERE id=? AND status='pending'",
        'idii',
        [$userId, round($sum, 2), count($items), $batchId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'The remittance was not confirmed.'];
    }
    mysqli_commit($conn);
    shaven_notify_remit_confirmed($conn, $userId, (int)$batches[0]['collector_id'], (string)$batches[0]['ref'], count($items), round($sum, 2));
    return [
        'ok' => true,
        'error' => '',
        'ref' => (string)$batches[0]['ref'],
        'count' => count($items),
        'amount' => round($sum, 2),
    ];
}
