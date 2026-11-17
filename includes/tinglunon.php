<?php
function shaven_tinglunon_payment_label($status) {
    return (int)$status === 1 ? 'Paid' : 'Unpaid';
}

function shaven_tinglunon_payment_badge($label) {
    $label = $label === 'Paid' ? 'Paid' : 'Unpaid';
    $class = $label === 'Paid' ? 'badge-success' : 'badge-warning';
    return '<span class="badge ' . $class . '">' . h($label) . '</span>';
}

function shaven_tinglunon_report($conn) {
    $asOf = date('Y-m-d');
    $rows = shaven_rows(
        $conn,
        "SELECT p.payment_amount, p.payment_year, p.payment_month, p.payment_day,
                p.period_start, p.period_end, p.due_date, p.status AS pay_status,
                s.id AS sid, s.s_name, s.s_address, s.s_doi, s.account_no, s.status AS sub_status,
                pl.p_name
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
         LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
         WHERE p.status = 0
           AND s.status <> 2"
    );
    $groups = [];
    foreach ($rows as $row) {
        $due = shaven_due_sort_value($row, $row['s_doi']);
        if ($due === '' || $due === '9999-12-31' || $due > $asOf) {
            continue;
        }
        $id = (int)$row['sid'];
        if (!isset($groups[$id])) {
            $groups[$id] = [
                'id' => $id,
                'name' => trim((string)$row['s_name']),
                'account' => trim((string)($row['account_no'] ?? '')),
                'address' => trim((string)($row['s_address'] ?? '')),
                'plan' => trim((string)($row['p_name'] ?? '')),
                'status' => (int)$row['sub_status'],
                'bills' => [],
            ];
        }
        $groups[$id]['bills'][] = [
            'due' => $due,
            'due_label' => shaven_due_text($row, $row['s_doi']) !== '' ? shaven_due_text($row, $row['s_doi']) : 'No due date',
            'period' => shaven_bill_period_label($row, $row['s_doi']),
            'amount' => round((float)$row['payment_amount'], 2),
            'payment_status' => shaven_tinglunon_payment_label($row['pay_status'] ?? 0),
        ];
    }
    $list = array_values($groups);
    foreach ($list as $index => $group) {
        usort($list[$index]['bills'], function ($left, $right) {
            return strcmp($left['due'], $right['due']);
        });
        $total = 0.0;
        $payLabels = [];
        foreach ($list[$index]['bills'] as $bill) {
            $total += (float)$bill['amount'];
            $payLabels[$bill['payment_status']] = true;
        }
        $list[$index]['payment_status'] = count($payLabels) === 1 ? (string)array_key_first($payLabels) : 'Unpaid';
        $list[$index]['earliest'] = $list[$index]['bills'][0]['due'];
        $list[$index]['total'] = round($total, 2);
        $list[$index]['count'] = count($list[$index]['bills']);
    }
    usort($list, function ($left, $right) {
        $byDue = strcmp($left['earliest'], $right['earliest']);
        if ($byDue !== 0) {
            return $byDue;
        }
        return strcasecmp($left['name'], $right['name']);
    });
    $billCount = 0;
    $amount = 0.0;
    foreach ($list as $group) {
        $billCount += (int)$group['count'];
        $amount += (float)$group['total'];
    }
    return [
        'groups' => $list,
        'subscribers' => count($list),
        'count' => $billCount,
        'total' => round($amount, 2),
        'as_of' => $asOf,
    ];
}
