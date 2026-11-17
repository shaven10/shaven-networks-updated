<?php
function shaven_notify_install($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_notifications (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        actor_id INT NULL DEFAULT NULL,
        kind VARCHAR(32) NOT NULL DEFAULT 'info',
        title VARCHAR(160) NOT NULL,
        body VARCHAR(500) NOT NULL DEFAULT '',
        link VARCHAR(255) NOT NULL DEFAULT '',
        is_read TINYINT NOT NULL DEFAULT 0,
        dedupe_key VARCHAR(80) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_dedupe (user_id, dedupe_key),
        KEY user_read (user_id, is_read, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function &shaven_notify_cache() {
    static $cache = ['unread' => []];
    return $cache;
}

function shaven_notify_clip($value, $max) {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function shaven_notify_count($count, $singular, $plural = null) {
    $count = (int)$count;
    $word = $count === 1 ? $singular : ($plural === null ? $singular . 's' : $plural);
    return $count . ' ' . $word;
}

function shaven_notify_icon($kind) {
    $map = [
        'payment' => 'fas fa-money-bill-wave',
        'remittance' => 'fas fa-wallet',
        'revert' => 'fas fa-undo',
        'plan' => 'fas fa-exchange-alt',
        'billing' => 'fas fa-file-invoice-dollar',
        'account' => 'fas fa-user-plus',
        'announce' => 'fas fa-bullhorn',
        'due' => 'fas fa-exclamation-circle',
        'holding' => 'fas fa-hand-holding-usd',
    ];
    return $map[$kind] ?? 'fas fa-bell';
}

function shaven_notify_kind_label($kind) {
    $map = [
        'payment' => 'Payment',
        'remittance' => 'Remittance',
        'revert' => 'Revert',
        'plan' => 'Plan',
        'billing' => 'Billing',
        'account' => 'Account',
        'announce' => 'Notice',
        'due' => 'Bill',
        'holding' => 'Collections',
    ];
    return $map[$kind] ?? 'Notice';
}

function shaven_notify_when($value) {
    $ts = strtotime((string)$value);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int)floor($diff / 60);
        return $mins . ' min ago';
    }
    if ($diff < 86400) {
        $hours = (int)floor($diff / 3600);
        return $hours . ($hours === 1 ? ' hr ago' : ' hrs ago');
    }
    if ($diff < 604800) {
        $days = (int)floor($diff / 86400);
        return $days . ($days === 1 ? ' day ago' : ' days ago');
    }
    return shaven_date($value, 'M j, Y g:i A');
}

function shaven_notify_safe_path($link) {
    $link = str_replace('\\', '/', trim((string)$link));
    if ($link === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $link) || strpos($link, '..') !== false) {
        return 'pages/account/notifications.php';
    }
    $link = ltrim($link, '/');
    if (stripos($link, '%2f') !== false || stripos($link, '%5c') !== false || stripos($link, '%00') !== false) {
        return 'pages/account/notifications.php';
    }
    if (!preg_match('#^[A-Za-z0-9_./?&=,%+-]+$#', $link)) {
        return 'pages/account/notifications.php';
    }
    return $link;
}

function shaven_notify_open_url($id) {
    return shaven_url('pages/account/notifications.php?open=' . (int)$id);
}

function shaven_notify_user_ids($conn, array $levels, $exceptId = 0) {
    $clean = [];
    foreach ($levels as $level) {
        $level = (int)$level;
        if ($level >= 1 && $level <= 4) {
            $clean[$level] = $level;
        }
    }
    if (!$clean) {
        return [];
    }
    $rows = shaven_rows(
        $conn,
        "SELECT id FROM tbl_sys_users WHERE status=1 AND sys_level IN (" . implode(',', $clean) . ")"
    );
    $exceptId = (int)$exceptId;
    $ids = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if ($id > 0 && $id !== $exceptId) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function shaven_notify_subscriber_user($conn, $subscriberId) {
    $rows = shaven_rows(
        $conn,
        "SELECT id FROM tbl_sys_users WHERE subscriber_id=? AND sys_level=4 AND status=1 ORDER BY id LIMIT 1",
        'i',
        [(int)$subscriberId]
    );
    return (int)($rows[0]['id'] ?? 0);
}

function shaven_notify_user_name($conn, $userId) {
    $rows = shaven_rows(
        $conn,
        "SELECT sys_user_fullname FROM tbl_sys_users WHERE id=? LIMIT 1",
        'i',
        [(int)$userId]
    );
    $name = trim((string)($rows[0]['sys_user_fullname'] ?? ''));
    return $name !== '' ? $name : 'Someone';
}

function shaven_notify($conn, $userIds, $kind, $title, $body, $link = '', $actorId = 0, $dedupe = '') {
    if (!($conn instanceof mysqli)) {
        return 0;
    }
    if (!is_array($userIds)) {
        $userIds = [$userIds];
    }
    $kind = substr(preg_replace('/[^a-z0-9_-]/i', '', (string)$kind), 0, 32);
    if ($kind === '') {
        $kind = 'info';
    }
    $title = shaven_notify_clip($title, 160);
    $body = shaven_notify_clip($body, 500);
    $link = shaven_notify_clip($link, 255);
    $dedupe = shaven_notify_clip($dedupe, 80);
    if ($title === '') {
        return 0;
    }
    $actorId = (int)$actorId;
    $stamp = date('Y-m-d H:i:s');
    $sent = 0;
    $seen = [];
    foreach ($userIds as $userId) {
        $userId = (int)$userId;
        if ($userId < 1 || isset($seen[$userId])) {
            continue;
        }
        $seen[$userId] = true;
        if ($dedupe !== '') {
            $saved = shaven_exec(
                $conn,
                "INSERT INTO tbl_notifications (user_id, actor_id, kind, title, body, link, is_read, dedupe_key, created_at)
                 VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, 0, ?, ?)
                 ON DUPLICATE KEY UPDATE dedupe_key = dedupe_key",
                'iissssss',
                [$userId, $actorId, $kind, $title, $body, $link, $dedupe, $stamp]
            );
            if ($saved['ok'] && (int)$saved['affected'] === 1) {
                $sent++;
            }
            continue;
        }
        $saved = shaven_exec(
            $conn,
            "INSERT INTO tbl_notifications (user_id, actor_id, kind, title, body, link, is_read, created_at)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, 0, ?)",
            'iisssss',
            [$userId, $actorId, $kind, $title, $body, $link, $stamp]
        );
        if ($saved['ok']) {
            $sent++;
        }
    }
    if ($sent > 0) {
        $cache = &shaven_notify_cache();
        $cache['unread'] = [];
    }
    return $sent;
}

function shaven_notify_unread_count($conn, $userId) {
    $userId = (int)$userId;
    if ($userId < 1 || !($conn instanceof mysqli)) {
        return 0;
    }
    $cache = &shaven_notify_cache();
    if (isset($cache['unread'][$userId])) {
        return $cache['unread'][$userId];
    }
    $rows = shaven_rows(
        $conn,
        "SELECT COUNT(*) c FROM tbl_notifications WHERE user_id=? AND is_read=0",
        'i',
        [$userId]
    );
    $count = (int)($rows[0]['c'] ?? 0);
    $cache['unread'][$userId] = $count;
    return $count;
}

function shaven_notify_list($conn, $userId, $unreadOnly = false, $limit = 50) {
    $userId = (int)$userId;
    $limit = max(1, min(100, (int)$limit));
    if ($userId < 1) {
        return [];
    }
    $sql = "SELECT id, actor_id, kind, title, body, link, is_read, created_at
            FROM tbl_notifications WHERE user_id=?";
    if ($unreadOnly) {
        $sql .= " AND is_read=0";
    }
    $sql .= " ORDER BY id DESC LIMIT " . $limit;
    return shaven_rows($conn, $sql, 'i', [$userId]);
}

function shaven_notify_mark($conn, $userId, $id) {
    $id = (int)$id;
    $userId = (int)$userId;
    if ($id < 1 || $userId < 1) {
        return null;
    }
    $rows = shaven_rows(
        $conn,
        "SELECT id, link FROM tbl_notifications WHERE id=? AND user_id=? LIMIT 1",
        'ii',
        [$id, $userId]
    );
    if (!$rows) {
        return null;
    }
    shaven_exec(
        $conn,
        "UPDATE tbl_notifications SET is_read=1 WHERE id=? AND user_id=? AND is_read=0",
        'ii',
        [$id, $userId]
    );
    $cache = &shaven_notify_cache();
    unset($cache['unread'][$userId]);
    return $rows[0];
}

function shaven_notify_mark_all($conn, $userId) {
    $userId = (int)$userId;
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_notifications SET is_read=1 WHERE user_id=? AND is_read=0",
        'i',
        [$userId]
    );
    $cache = &shaven_notify_cache();
    unset($cache['unread'][$userId]);
    return $saved;
}

function shaven_notify_menu($conn, $userId) {
    $userId = (int)$userId;
    $unread = shaven_notify_unread_count($conn, $userId);
    $items = shaven_notify_list($conn, $userId, false, 8);
    ob_start();
    if ($unread > 0) {
        echo '<span class="dropdown-item dropdown-header">' . (int)$unread . ' unread</span>';
    } else {
        echo '<span class="dropdown-item dropdown-header">Notifications</span>';
    }
    if (!$items) {
        echo '<div class="dropdown-divider"></div>';
        echo '<span class="dropdown-item text-muted">No notifications yet.</span>';
    }
    foreach ($items as $item) {
        $unreadClass = (int)$item['is_read'] === 0 ? ' is-unread' : '';
        echo '<div class="dropdown-divider"></div>';
        echo '<a class="dropdown-item shaven-notify-item' . $unreadClass . '" href="' . h(shaven_notify_open_url($item['id'])) . '">';
        echo '<i class="' . h(shaven_notify_icon($item['kind'])) . '"></i>';
        echo '<span class="shaven-notify-copy">';
        echo '<strong>' . h($item['title']) . '</strong>';
        if (trim((string)$item['body']) !== '') {
            echo '<span>' . h($item['body']) . '</span>';
        }
        echo '<span class="shaven-notify-time">' . h(shaven_notify_when($item['created_at'])) . '</span>';
        echo '</span></a>';
    }
    echo '<div class="dropdown-divider"></div>';
    echo '<a class="dropdown-item dropdown-footer" href="' . h(shaven_url('pages/account/notifications.php')) . '">See all notifications</a>';
    return ['unread' => $unread, 'html' => ob_get_clean()];
}

function shaven_notify_announce($conn, $actorId, $audience, $title, $body) {
    if (!shaven_role_is(1, 2)) {
        return ['ok' => false, 'error' => 'Notices are sent by an admin or the owner.', 'sent' => 0];
    }
    $title = shaven_notify_clip($title, 160);
    $body = shaven_notify_clip($body, 500);
    if ($title === '' || $body === '') {
        return ['ok' => false, 'error' => 'Enter a title and a message.', 'sent' => 0];
    }
    $map = [
        'all' => [1, 2, 3, 4],
        '1' => [1],
        '2' => [2],
        '3' => [3],
        '4' => [4],
    ];
    $audience = (string)$audience;
    if (!isset($map[$audience])) {
        return ['ok' => false, 'error' => 'Choose who should receive this notice.', 'sent' => 0];
    }
    $ids = shaven_notify_user_ids($conn, $map[$audience], (int)$actorId);
    if (!$ids) {
        return ['ok' => false, 'error' => 'No other active account in that group can receive this notice.', 'sent' => 0];
    }
    $sent = shaven_notify($conn, $ids, 'announce', $title, $body, 'pages/account/notifications.php', (int)$actorId);
    if ($sent < 1) {
        return ['ok' => false, 'error' => 'The notice was not sent.', 'sent' => 0];
    }
    return ['ok' => true, 'error' => '', 'sent' => $sent];
}

function shaven_notify_refresh($conn) {
    $userId = (int)($_SESSION['usr_id'] ?? 0);
    if ($userId < 1 || !($conn instanceof mysqli)) {
        return;
    }
    $day = date('Y-m-d');
    if (($_SESSION['notify_sync'] ?? '') === $day) {
        return;
    }
    $role = shaven_role();
    if ($role === 4) {
        $subscriberId = (int)($_SESSION['subscriber_id'] ?? 0);
        if ($subscriberId > 0) {
            $rows = shaven_rows(
                $conn,
                "SELECT COUNT(*) c, COALESCE(SUM(payment_amount), 0) amt,
                        COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date > '1970-01-01' AND due_date < CURDATE() THEN 1 ELSE 0 END), 0) overdue
                 FROM tbl_payments WHERE payment_uid=? AND status=0",
                'i',
                [$subscriberId]
            );
            $count = (int)($rows[0]['c'] ?? 0);
            if ($count > 0) {
                $overdue = (int)($rows[0]['overdue'] ?? 0);
                $body = 'You have ' . shaven_notify_count($count, 'open bill') . ' totaling ₱' . number_format((float)$rows[0]['amt'], 2) . '.';
                if ($overdue === 1) {
                    $body .= ' 1 is past the due date.';
                } elseif ($overdue > 1) {
                    $body .= ' ' . $overdue . ' are past the due date.';
                }
                shaven_notify($conn, $userId, 'due', 'Open bills on your account', $body, 'pages/account/pay.php', 0, 'due:' . $day);
            }
        }
    } elseif ($role === 3) {
        $held = shaven_remit_held($conn, $userId, 1);
        if ((int)$held['count'] > 0) {
            shaven_notify(
                $conn,
                $userId,
                'holding',
                'Collections still with you',
                shaven_notify_count((int)$held['count'], 'payment') . ' totaling ₱' . number_format((float)$held['amount'], 2) . ' have not been submitted for remittance.',
                'pages/tables/remittances.php',
                0,
                'holding:' . $day
            );
        }
    } elseif ($role === 1 || $role === 2) {
        $held = shaven_remit_held($conn, $userId, 1);
        if ((int)$held['count'] > 0) {
            shaven_notify(
                $conn,
                $userId,
                'holding',
                'Collections still with you',
                shaven_notify_count((int)$held['count'], 'payment') . ' totaling ₱' . number_format((float)$held['amount'], 2) . ' have not been submitted for remittance.',
                'pages/tables/remittances.php',
                0,
                'holding:' . $day
            );
        }
        $totals = shaven_remit_confirm_totals($conn, $userId);
        if ((int)$totals['batches'] > 0) {
            shaven_notify(
                $conn,
                $userId,
                'remittance',
                'Remittances waiting',
                shaven_notify_count((int)$totals['batches'], 'batch', 'batches') . ' waiting for confirmation, covering ₱' . number_format((float)$totals['amount'], 2) . '.',
                'pages/tables/remittances.php',
                0,
                'remit-wait:' . $day
            );
        }
        $reverts = shaven_rows($conn, "SELECT COUNT(*) c FROM tbl_payments WHERE status=1 AND revert=1");
        $revertCount = (int)($reverts[0]['c'] ?? 0);
        if ($revertCount > 0) {
            shaven_notify(
                $conn,
                $userId,
                'revert',
                'Revert requests waiting',
                shaven_notify_count($revertCount, 'payment') . ' waiting for approval.',
                'pages/tables/collections_revert_approval.php',
                0,
                'revert-wait:' . $day
            );
        }
    }
    $_SESSION['notify_sync'] = $day;
}

function shaven_notify_payment_posted($conn, $subscriberId, $posterId, array $receiptIds, $viaPortal) {
    $ids = [];
    foreach ($receiptIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    if (!$ids) {
        return;
    }
    $subscriberId = (int)$subscriberId;
    $posterId = (int)$posterId;
    $profile = shaven_rows($conn, "SELECT s_name FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
    $name = trim((string)($profile[0]['s_name'] ?? ''));
    if ($name === '') {
        $name = 'A subscriber';
    }
    $sum = shaven_rows(
        $conn,
        "SELECT COALESCE(SUM(payment_amount), 0) amt FROM tbl_payments WHERE id IN (" . implode(',', $ids) . ")"
    );
    $money = '₱' . number_format((float)($sum[0]['amt'] ?? 0), 2);
    $covered = shaven_notify_count(count($ids), 'bill');
    $portalUser = shaven_notify_subscriber_user($conn, $subscriberId);
    $receiptLink = 'pages/tables/receipt.php?ids=' . implode(',', $ids);
    $accountLink = 'pages/tables/subscriber_view.php?uid=' . $subscriberId;
    if ($viaPortal) {
        if ($portalUser > 0) {
            shaven_notify($conn, $portalUser, 'payment', 'Payment posted', 'Your payment of ' . $money . ' covering ' . $covered . ' was recorded.', $receiptLink, $posterId);
        }
        shaven_notify(
            $conn,
            shaven_notify_user_ids($conn, [1, 2], $posterId),
            'payment',
            'Subscriber payment',
            $name . ' paid ' . $money . ' through the subscriber portal.',
            $accountLink,
            $posterId
        );
        return;
    }
    $poster = shaven_notify_user_name($conn, $posterId);
    if ($portalUser > 0 && $portalUser !== $posterId) {
        shaven_notify(
            $conn,
            $portalUser,
            'payment',
            'Payment posted on your account',
            $poster . ' recorded ' . $money . ' covering ' . $covered . '.',
            'pages/account/index.php',
            $posterId
        );
    }
    $posterLevel = shaven_remit_level($conn, $posterId);
    shaven_notify(
        $conn,
        shaven_notify_user_ids($conn, [1, 2], $posterId),
        'payment',
        $posterLevel === 3 ? 'Field payment posted' : 'Payment posted',
        $poster . ' recorded ' . $money . ' for ' . $name . '.',
        $accountLink,
        $posterId
    );
}

function shaven_notify_remit_submitted($conn, $userId, $ref, $count, $amount) {
    $userId = (int)$userId;
    shaven_notify(
        $conn,
        shaven_notify_user_ids($conn, [1, 2], $userId),
        'remittance',
        'Remittance submitted',
        shaven_notify_user_name($conn, $userId) . ' submitted ' . $ref . ' with ' . shaven_notify_count((int)$count, 'payment') . ' totaling ₱' . number_format((float)$amount, 2) . '.',
        'pages/tables/remittances.php',
        $userId
    );
}

function shaven_notify_remit_confirmed($conn, $confirmerId, $collectorId, $ref, $count, $amount) {
    $confirmerId = (int)$confirmerId;
    $collectorId = (int)$collectorId;
    if ($collectorId < 1 || $collectorId === $confirmerId) {
        return;
    }
    shaven_notify(
        $conn,
        $collectorId,
        'remittance',
        'Remittance confirmed',
        shaven_notify_user_name($conn, $confirmerId) . ' confirmed ' . $ref . '. ' . shaven_notify_count((int)$count, 'payment') . ' totaling ₱' . number_format((float)$amount, 2) . ' recorded as received.',
        'pages/tables/remittances.php',
        $confirmerId
    );
}

function shaven_notify_payment_fact($conn, $paymentId) {
    $rows = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_amount, p.payment_uid, p.postedby, p.revert_by, s.s_name
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
         WHERE p.id=? LIMIT 1",
        'i',
        [(int)$paymentId]
    );
    return $rows[0] ?? null;
}

function shaven_notify_revert_requested($conn, $actorId, array $fact) {
    $name = trim((string)($fact['s_name'] ?? ''));
    if ($name === '') {
        $name = 'a subscriber';
    }
    $money = '₱' . number_format((float)($fact['payment_amount'] ?? 0), 2);
    shaven_notify(
        $conn,
        shaven_notify_user_ids($conn, [1, 2], (int)$actorId),
        'revert',
        'Revert requested',
        shaven_notify_user_name($conn, $actorId) . ' asked to revert a ' . $money . ' payment for ' . $name . '.',
        'pages/tables/collections_revert_approval.php',
        (int)$actorId
    );
}

function shaven_notify_revert_cancelled($conn, $actorId, array $fact) {
    $actorId = (int)$actorId;
    $name = trim((string)($fact['s_name'] ?? ''));
    if ($name === '') {
        $name = 'a subscriber';
    }
    $targets = shaven_notify_user_ids($conn, [1, 2], $actorId);
    $requester = (int)($fact['revert_by'] ?? 0);
    if ($requester > 0 && $requester !== $actorId) {
        $targets[] = $requester;
    }
    shaven_notify(
        $conn,
        $targets,
        'revert',
        'Revert request cancelled',
        shaven_notify_user_name($conn, $actorId) . ' cancelled the revert request for ' . $name . '. The payment stays paid.',
        'pages/tables/collections_revert.php',
        $actorId
    );
}

function shaven_notify_revert_approved($conn, $actorId, array $fact) {
    $actorId = (int)$actorId;
    $name = trim((string)($fact['s_name'] ?? ''));
    if ($name === '') {
        $name = 'a subscriber';
    }
    $money = '₱' . number_format((float)($fact['payment_amount'] ?? 0), 2);
    $portal = shaven_notify_subscriber_user($conn, (int)($fact['payment_uid'] ?? 0));
    $staff = [];
    foreach ([(int)($fact['revert_by'] ?? 0), (int)($fact['postedby'] ?? 0)] as $id) {
        if ($id > 0 && $id !== $actorId && $id !== $portal) {
            $staff[$id] = $id;
        }
    }
    if ($staff) {
        shaven_notify(
            $conn,
            array_values($staff),
            'revert',
            'Revert approved',
            'The ' . $money . ' payment for ' . $name . ' was reverted. That bill is unpaid again.',
            'pages/tables/collections_revert.php',
            $actorId
        );
    }
    if ($portal > 0 && $portal !== $actorId) {
        shaven_notify(
            $conn,
            $portal,
            'revert',
            'A payment was reverted',
            'A ' . $money . ' payment on your account was reverted. The bill is unpaid again.',
            'pages/account/pay.php',
            $actorId
        );
    }
}

function shaven_notify_revert_closed($conn, $actorId, array $fact) {
    $actorId = (int)$actorId;
    $requester = (int)($fact['revert_by'] ?? 0);
    if ($requester < 1 || $requester === $actorId) {
        return;
    }
    $name = trim((string)($fact['s_name'] ?? ''));
    if ($name === '') {
        $name = 'a subscriber';
    }
    shaven_notify(
        $conn,
        $requester,
        'revert',
        'Revert request closed',
        'The request for ' . $name . ' was closed because the payment was already remitted. It stays paid.',
        'pages/tables/collections_revert.php',
        $actorId
    );
}

function shaven_notify_plan_changed($conn, $subscriberId, $userId, $oldName, $newName) {
    $subscriberId = (int)$subscriberId;
    $userId = (int)$userId;
    $profile = shaven_rows($conn, "SELECT s_name FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
    $name = trim((string)($profile[0]['s_name'] ?? ''));
    if ($name === '') {
        $name = 'A subscriber';
    }
    $oldName = trim((string)$oldName);
    $newName = trim((string)$newName);
    if ($oldName === '') {
        $oldName = 'the previous plan';
    }
    if ($newName === '') {
        $newName = 'a new plan';
    }
    $portal = shaven_notify_subscriber_user($conn, $subscriberId);
    if (shaven_remit_level($conn, $userId) === 4) {
        shaven_notify(
            $conn,
            shaven_notify_user_ids($conn, [1, 2], $userId),
            'plan',
            'Plan changed',
            $name . ' moved from ' . $oldName . ' to ' . $newName . '.',
            'pages/tables/subscriber_view.php?uid=' . $subscriberId,
            $userId
        );
        return;
    }
    if ($portal > 0 && $portal !== $userId) {
        shaven_notify(
            $conn,
            $portal,
            'plan',
            'Your plan changed',
            'Your service moved from ' . $oldName . ' to ' . $newName . '.',
            'pages/account/plan.php',
            $userId
        );
    }
}

function shaven_notify_bills_created($conn, array $bySubscriber, $created) {
    $created = (int)$created;
    if ($created < 1 || !$bySubscriber) {
        return;
    }
    $actor = (int)($_SESSION['usr_id'] ?? 0);
    $actorName = $actor > 0 ? shaven_notify_user_name($conn, $actor) : 'The office';
    $subIds = [];
    foreach (array_keys($bySubscriber) as $subId) {
        $subId = (int)$subId;
        if ($subId > 0) {
            $subIds[$subId] = $subId;
        }
    }
    $portals = [];
    if ($subIds) {
        $rows = shaven_rows(
            $conn,
            "SELECT id, subscriber_id FROM tbl_sys_users WHERE status=1 AND sys_level=4 AND subscriber_id IN (" . implode(',', $subIds) . ")"
        );
        foreach ($rows as $row) {
            $portals[(int)$row['subscriber_id']] = (int)$row['id'];
        }
    }
    foreach ($bySubscriber as $subId => $info) {
        $subId = (int)$subId;
        $userId = $portals[$subId] ?? 0;
        if ($userId < 1 || $userId === $actor) {
            continue;
        }
        $count = (int)($info['count'] ?? 0);
        if ($count < 1) {
            continue;
        }
        $who = trim((string)($info['name'] ?? ''));
        $body = shaven_notify_count($count, 'new bill') . ' added';
        if ($who !== '') {
            $body .= ' for ' . $who;
        }
        $body .= '.';
        shaven_notify($conn, $userId, 'billing', 'New bill on your account', $body, 'pages/account/pay.php', $actor);
    }
    shaven_notify(
        $conn,
        shaven_notify_user_ids($conn, [1, 2], $actor),
        'billing',
        'Bills created',
        $actorName . ' created ' . shaven_notify_count($created, 'bill') . '.',
        'pages/admin/billing.php',
        $actor
    );
}

function shaven_notify_subscriber_added($conn, $subscriberId, $name) {
    $subscriberId = (int)$subscriberId;
    $name = trim((string)$name);
    if ($subscriberId < 1 || $name === '') {
        return;
    }
    $actor = (int)($_SESSION['usr_id'] ?? 0);
    $who = $actor > 0 ? shaven_notify_user_name($conn, $actor) : 'The office';
    shaven_notify(
        $conn,
        shaven_notify_user_ids($conn, [1, 2, 3], $actor),
        'account',
        'New subscriber',
        $who . ' registered ' . $name . '.',
        'pages/tables/subscriber_view.php?uid=' . $subscriberId,
        $actor
    );
}

function shaven_notify_subscriber_status($conn, $subscriberId, $active) {
    $subscriberId = (int)$subscriberId;
    if ($subscriberId < 1) {
        return;
    }
    $rows = shaven_rows($conn, "SELECT s_name FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
    if (!$rows) {
        return;
    }
    $name = trim((string)$rows[0]['s_name']);
    if ($name === '') {
        $name = 'A subscriber';
    }
    $actor = (int)($_SESSION['usr_id'] ?? 0);
    $portal = shaven_notify_subscriber_user($conn, $subscriberId);
    $active = (bool)$active;
    if ($portal > 0 && $portal !== $actor) {
        shaven_notify(
            $conn,
            $portal,
            'account',
            $active ? 'Service activated' : 'Service deactivated',
            $active ? 'Your subscriber account is active.' : 'Your subscriber account is inactive.',
            'pages/account/index.php',
            $actor
        );
    }
    shaven_notify(
        $conn,
        shaven_notify_user_ids($conn, [1, 2, 3], $actor),
        'account',
        $active ? 'Subscriber activated' : 'Subscriber deactivated',
        $name . ($active ? ' is active.' : ' is inactive.'),
        'pages/tables/subscriber_view.php?uid=' . $subscriberId,
        $actor
    );
}
