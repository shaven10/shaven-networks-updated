<?php
function shaven_collection_record_views($role) {
    $views = [
        'paid' => 'Paid this month',
        'unpaid' => 'Unpaid this month',
        'overdue' => 'Overdue backlogs',
        'monthly' => 'Monthly collection',
        'posted' => 'Filtered reports',
    ];
    if ((int)$role === 3) {
        unset($views['paid'], $views['monthly'], $views['posted']);
    }
    return $views;
}

function shaven_collection_record_day($value) {
    $value = trim((string)$value);
    $parsed = $value !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    if (!$parsed instanceof DateTimeImmutable || $parsed->format('Y-m-d') !== $value) {
        return '';
    }
    return $value;
}

function shaven_collection_record_query($conn, $view, $monthValue, $fromValue = '', $toValue = '') {
    $now = new DateTimeImmutable('now');
    $year = (int)$now->format('Y');
    $monthName = $now->format('F');
    $where = 's.status=1';
    $types = '';
    $params = [];
    $label = '';
    if ($view === 'paid') {
        $where .= ' AND p.status=1 AND p.payment_year=? AND p.payment_month=?';
        $types = 'is';
        $params = [$year, $monthName];
        $label = 'Paid bills for ' . $monthName . ' ' . $year;
    } elseif ($view === 'unpaid') {
        $where .= ' AND p.status=0 AND p.payment_month=?';
        $types = 's';
        $params = [$monthName];
        $label = 'Unpaid bills for ' . $monthName;
    } elseif ($view === 'overdue') {
        $where .= ' AND p.status=0 AND p.payment_month<>?';
        $types = 's';
        $params = [$monthName];
        $label = 'Unpaid bills outside ' . $monthName;
    } elseif ($view === 'posted') {
        $where .= ' AND p.status=1';
        $label = 'All paid bills by the date they were posted';
        $fromDay = shaven_collection_record_day($fromValue);
        $toDay = shaven_collection_record_day($toValue);
        if ($fromDay !== '' && $toDay !== '' && $fromDay > $toDay) {
            $swap = $fromDay;
            $fromDay = $toDay;
            $toDay = $swap;
        }
        if ($fromDay !== '') {
            $where .= ' AND DATE(p.payment_date)>=?';
            $types .= 's';
            $params[] = $fromDay;
        }
        if ($toDay !== '') {
            $where .= ' AND DATE(p.payment_date)<=?';
            $types .= 's';
            $params[] = $toDay;
        }
        if ($fromDay !== '' && $toDay !== '') {
            $label = 'Paid bills posted from ' . shaven_date($fromDay, 'F j, Y') . ' through ' . shaven_date($toDay, 'F j, Y');
        } elseif ($fromDay !== '') {
            $label = 'Paid bills posted on or after ' . shaven_date($fromDay, 'F j, Y');
        } elseif ($toDay !== '') {
            $label = 'Paid bills posted through ' . shaven_date($toDay, 'F j, Y');
        }
    } else {
        $where .= ' AND p.status=1';
        $label = 'All paid bills';
        $monthValue = trim((string)$monthValue);
        $parsed = $monthValue !== '' ? DateTimeImmutable::createFromFormat('!Y-m', $monthValue) : false;
        if ($parsed instanceof DateTimeImmutable) {
            $where .= ' AND p.payment_year=? AND p.payment_month=?';
            $types = 'is';
            $params = [(int)$parsed->format('Y'), $parsed->format('F')];
            $label = 'Paid bills for ' . $parsed->format('F Y');
        }
    }
    $rows = shaven_rows(
        $conn,
        "SELECT p.id AS pid, p.payment_amount, p.payment_date, p.status AS stat,
                p.payment_year, p.payment_month, p.payment_day, p.period_start, p.period_end, p.due_date,
                s.id, s.s_name, s.s_doi, pl.p_name, pl.p_speed_kb, u.sys_user_fullname AS posted_name
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
         INNER JOIN tbl_plans pl ON pl.id = s.plan_id
         LEFT JOIN tbl_sys_users u ON u.id = p.postedby
         WHERE {$where}
         ORDER BY s.s_name, p.id",
        $types,
        $params
    );
    return ['rows' => $rows, 'label' => $label];
}
