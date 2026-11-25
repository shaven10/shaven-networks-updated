<?php

function shaven_ledger_install($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_collector_ledger (
        id INT NOT NULL AUTO_INCREMENT,
        collector_id INT NOT NULL,
        entry_date DATE NOT NULL,
        entry_type VARCHAR(20) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL,
        updated_by INT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY collector_date (collector_id, entry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function shaven_ledger_types() {
    return [
        'opening' => ['label' => 'Previous balance', 'side' => 'out'],
        'advance' => ['label' => 'Cash advance', 'side' => 'out'],
        'charge' => ['label' => 'Other charge', 'side' => 'out'],
        'salary' => ['label' => 'Monthly salary', 'side' => 'in'],
        'labor' => ['label' => 'Labor / job', 'side' => 'in'],
        'repayment' => ['label' => 'Payment from collector', 'side' => 'in'],
        'credit' => ['label' => 'Other credit', 'side' => 'in'],
    ];
}

function shaven_ledger_side($type) {
    $types = shaven_ledger_types();
    return $types[$type]['side'] ?? 'out';
}

function shaven_ledger_can_edit() {
    return shaven_role_is(1);
}

function shaven_ledger_can_view() {
    return shaven_role_is(1, 3);
}

function shaven_ledger_collectors($conn) {
    return shaven_rows(
        $conn,
        "SELECT id, sys_user_fullname, username, status FROM tbl_sys_users WHERE sys_level=3 ORDER BY sys_user_fullname"
    );
}

function shaven_ledger_collector($conn, $collectorId) {
    $rows = shaven_rows(
        $conn,
        "SELECT id, sys_user_fullname, username, status FROM tbl_sys_users WHERE id=? AND sys_level=3 LIMIT 1",
        'i',
        [(int)$collectorId]
    );
    return $rows[0] ?? null;
}

function shaven_ledger_entries($conn, $collectorId) {
    $rows = shaven_rows(
        $conn,
        "SELECT l.*, u.sys_user_fullname AS updated_name
         FROM tbl_collector_ledger l
         LEFT JOIN tbl_sys_users u ON u.id = COALESCE(l.updated_by, l.created_by)
         WHERE l.collector_id=?
         ORDER BY l.entry_date, l.id",
        'i',
        [(int)$collectorId]
    );
    $balance = 0.0;
    $totalIn = 0.0;
    $totalOut = 0.0;
    foreach ($rows as &$row) {
        $amount = round((float)$row['amount'], 2);
        $row['side'] = shaven_ledger_side($row['entry_type']);
        if ($row['side'] === 'in') {
            $totalIn += $amount;
            $balance -= $amount;
        } else {
            $totalOut += $amount;
            $balance += $amount;
        }
        $row['balance'] = round($balance, 2);
    }
    unset($row);
    return [
        'rows' => $rows,
        'in' => round($totalIn, 2),
        'out' => round($totalOut, 2),
        'balance' => round($balance, 2),
        'as_of' => $rows ? end($rows)['entry_date'] : null,
    ];
}

function shaven_ledger_balance_label($balance) {
    $balance = round((float)$balance, 2);
    if ($balance > 0) {
        return 'Collector owes';
    }
    if ($balance < 0) {
        return 'Owner owes collector';
    }
    return 'Settled';
}

function shaven_ledger_clean(array $input) {
    $types = shaven_ledger_types();
    $type = (string)($input['entry_type'] ?? '');
    if (!isset($types[$type])) {
        return ['ok' => false, 'error' => 'Choose an entry type.'];
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($input['entry_date'] ?? ''));
    if (!$date) {
        return ['ok' => false, 'error' => 'Choose a real date for the entry.'];
    }
    $amount = round((float)str_replace([',', ' '], '', (string)($input['amount'] ?? '')), 2);
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Enter an amount greater than zero.'];
    }
    $description = trim(preg_replace('/\s+/', ' ', (string)($input['description'] ?? '')));
    if ($description === '') {
        $description = $types[$type]['label'];
    }
    return [
        'ok' => true,
        'type' => $type,
        'date' => $date->format('Y-m-d'),
        'amount' => $amount,
        'description' => mb_substr($description, 0, 255),
    ];
}

function shaven_ledger_save($conn, $collectorId, array $input, $userId, $entryId = 0) {
    if (!shaven_ledger_can_edit()) {
        return ['ok' => false, 'error' => 'Only the owner can update a collector ledger.'];
    }
    if (!shaven_ledger_collector($conn, $collectorId)) {
        return ['ok' => false, 'error' => 'Choose a collector account.'];
    }
    $clean = shaven_ledger_clean($input);
    if (!$clean['ok']) {
        return $clean;
    }
    $entryId = (int)$entryId;
    if ($entryId > 0) {
        $saved = shaven_exec(
            $conn,
            "UPDATE tbl_collector_ledger
             SET entry_date=?, entry_type=?, description=?, amount=?, updated_by=?, updated_at=NOW()
             WHERE id=? AND collector_id=?",
            'sssdiii',
            [$clean['date'], $clean['type'], $clean['description'], $clean['amount'], (int)$userId, $entryId, (int)$collectorId]
        );
        if (!$saved['ok']) {
            return ['ok' => false, 'error' => 'The entry was not updated.'];
        }
        return ['ok' => true, 'error' => 'Ledger entry updated.'];
    }
    $saved = shaven_exec(
        $conn,
        "INSERT INTO tbl_collector_ledger (collector_id, entry_date, entry_type, description, amount, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        'isssdi',
        [(int)$collectorId, $clean['date'], $clean['type'], $clean['description'], $clean['amount'], (int)$userId]
    );
    if (!$saved['ok']) {
        return ['ok' => false, 'error' => 'The entry was not saved.'];
    }
    return ['ok' => true, 'error' => 'Ledger entry added.'];
}

function shaven_ledger_delete($conn, $collectorId, $entryId) {
    if (!shaven_ledger_can_edit()) {
        return ['ok' => false, 'error' => 'Only the owner can delete a ledger entry.'];
    }
    $deleted = shaven_exec(
        $conn,
        "DELETE FROM tbl_collector_ledger WHERE id=? AND collector_id=?",
        'ii',
        [(int)$entryId, (int)$collectorId]
    );
    if (!$deleted['ok'] || (int)$deleted['affected'] === 0) {
        return ['ok' => false, 'error' => 'That entry was not found.'];
    }
    return ['ok' => true, 'error' => 'Ledger entry deleted.'];
}
