<?php
if (defined('SHAVEN_BOOT')) {
    return;
}
define('SHAVEN_BOOT', '1');
define('SHAVEN_ROOT', dirname(__DIR__));

date_default_timezone_set('Asia/Manila');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function shaven_base_path() {
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $root = realpath(SHAVEN_ROOT);
    if (!$doc || !$root) {
        $base = '';
        return $base;
    }
    $doc = rtrim(str_replace('\\', '/', $doc), '/');
    $root = rtrim(str_replace('\\', '/', $root), '/');
    if (stripos($root, $doc) !== 0) {
        $base = '';
        return $base;
    }
    $rel = substr($root, strlen($doc));
    $parts = array_values(array_filter(explode('/', $rel), 'strlen'));
    $base = $parts ? '/' . implode('/', array_map('rawurlencode', $parts)) : '';
    return $base;
}

function shaven_url($path) {
    return shaven_base_path() . '/' . ltrim($path, '/');
}

function shaven_account_photo_url($photo) {
    $photo = str_replace('\\', '/', (string)$photo);
    if (preg_match('#^uploads/profiles/[A-Za-z0-9._-]+$#', $photo)) {
        $full = SHAVEN_ROOT . '/' . $photo;
        if (is_file($full)) {
            return shaven_url($photo);
        }
    }
    return shaven_url('dist/img/user2-160x160.jpg');
}

function shaven_redirect($path) {
    header('Location: ' . shaven_url($path));
    exit;
}

function shaven_rel_script() {
    $root = realpath(SHAVEN_ROOT);
    $file = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (!$root || !$file || stripos($file, $root) !== 0) {
        return '';
    }
    return str_replace('\\', '/', substr($file, strlen($root)));
}

function shaven_role() {
    return (int)($_SESSION['sys_level'] ?? 0);
}

function shaven_role_is(int ...$roles) {
    return in_array(shaven_role(), $roles, true);
}

function shaven_role_name($level = null) {
    $map = [
        1 => 'Owner',
        2 => 'Admin',
        3 => 'Technician',
        4 => 'Subscriber',
    ];
    $level = $level === null ? shaven_role() : (int)$level;
    return $map[$level] ?? 'Account';
}

function shaven_role_badge($level) {
    $class = [1 => 'dark', 2 => 'primary', 3 => 'warning', 4 => 'success'][(int)$level] ?? 'secondary';
    return '<span class="badge badge-' . $class . '">' . h(shaven_role_name($level)) . '</span>';
}

function shaven_status_badge($status) {
    $status = (int)$status;
    if ($status === 1) {
        return '<span class="badge badge-success">Active</span>';
    }
    if ($status === 2) {
        return '<span class="badge badge-danger">Inactive</span>';
    }
    return '<span class="badge badge-warning">Pending</span>';
}

function shaven_payment_status_badge($status) {
    if ((int)$status === 1) {
        return '<span class="badge badge-success">Paid</span>';
    }
    return '<span class="badge badge-warning">Unpaid</span>';
}

function shaven_peso($amount) {
    return '&#8369;' . number_format((float)$amount, 2);
}

function shaven_speed_label($kb) {
    $kb = (int)$kb;
    if ($kb >= 1000) {
        $mbps = $kb / 1000;
        $label = (floor($mbps) == $mbps) ? (string)(int)$mbps : rtrim(rtrim(number_format($mbps, 1, '.', ''), '0'), '.');
        return $label . ' Mbps';
    }
    if ($kb > 0) {
        return $kb . ' kbps';
    }
    return 'Speed on file';
}

function shaven_date($value, $format = 'M j, Y') {
    if ($value === null || $value === '' || strpos((string)$value, '0000-00-00') === 0) {
        return '—';
    }
    $ts = strtotime((string)$value);
    if (!$ts) {
        return h($value);
    }
    return date($format, $ts);
}

function shaven_install_cell($value) {
    $raw = (string)$value;
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return '<td data-order="9999-12-31"><span class="shaven-install-date">No installation date</span></td>';
    }
    $ts = strtotime($raw);
    if (!$ts) {
        return '<td>' . h($raw) . '</td>';
    }
    return '<td data-order="' . date('Y-m-d', $ts) . '"><span class="shaven-install-date">' . h(date('F j, Y', $ts)) . '</span></td>';
}

function shaven_account_year(array $row) {
    $installed = shaven_install_date($row['s_doi'] ?? '');
    if ($installed) {
        return (int)$installed->format('Y');
    }
    $stamp = strtotime((string)($row['timestamp'] ?? ''));
    if ($stamp) {
        return (int)date('Y', $stamp);
    }
    return (int)date('Y');
}

function shaven_format_account_number($year, $seq) {
    return sprintf('SN-%04d-%04d', (int)$year, (int)$seq);
}

function shaven_parse_account_number($value) {
    if (!preg_match('/^SN-(\d{4})-(\d+)$/', trim((string)$value), $match)) {
        return null;
    }
    return ['year' => (int)$match[1], 'seq' => (int)$match[2]];
}

function shaven_account_sort_key(array $row) {
    $installed = shaven_install_date($row['s_doi'] ?? '');
    return [
        shaven_account_year($row),
        $installed ? $installed->format('Y-m-d') : '9999-12-31',
        (int)$row['id'],
    ];
}

function shaven_assign_missing_account_numbers($conn) {
    $count = shaven_rows($conn, "SELECT COUNT(*) c FROM tbl_subs_profile WHERE account_no IS NULL OR account_no=''");
    if (!$count || (int)$count[0]['c'] === 0) {
        return;
    }
    mysqli_begin_transaction($conn);
    $rows = shaven_rows($conn, "SELECT id, s_doi, timestamp, account_no FROM tbl_subs_profile FOR UPDATE");
    $max = [];
    $missing = [];
    foreach ($rows as $row) {
        $parsed = shaven_parse_account_number($row['account_no'] ?? '');
        if ($parsed) {
            $max[$parsed['year']] = max($max[$parsed['year']] ?? 0, $parsed['seq']);
            continue;
        }
        $missing[] = $row;
    }
    usort($missing, function ($left, $right) {
        $a = shaven_account_sort_key($left);
        $b = shaven_account_sort_key($right);
        if ($a[0] !== $b[0]) {
            return $a[0] <=> $b[0];
        }
        if ($a[1] !== $b[1]) {
            return strcmp($a[1], $b[1]);
        }
        return $a[2] <=> $b[2];
    });
    foreach ($missing as $row) {
        $year = shaven_account_year($row);
        $seq = ($max[$year] ?? 0) + 1;
        $max[$year] = $seq;
        $saved = shaven_exec(
            $conn,
            "UPDATE tbl_subs_profile SET account_no=? WHERE id=? AND (account_no IS NULL OR account_no='')",
            'si',
            [shaven_format_account_number($year, $seq), (int)$row['id']]
        );
        if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
            mysqli_rollback($conn);
            return;
        }
    }
    mysqli_commit($conn);
}

function shaven_assign_account_number($conn, $subscriberId) {
    $subscriberId = (int)$subscriberId;
    if ($subscriberId < 1) {
        return '';
    }
    mysqli_begin_transaction($conn);
    $rows = shaven_rows(
        $conn,
        "SELECT id, s_doi, timestamp, account_no FROM tbl_subs_profile WHERE id=? LIMIT 1 FOR UPDATE",
        'i',
        [$subscriberId]
    );
    if (!$rows) {
        mysqli_rollback($conn);
        return '';
    }
    $parsed = shaven_parse_account_number($rows[0]['account_no'] ?? '');
    if ($parsed) {
        mysqli_commit($conn);
        return shaven_format_account_number($parsed['year'], $parsed['seq']);
    }
    $year = shaven_account_year($rows[0]);
    $prefix = sprintf('SN-%04d-', $year);
    $taken = shaven_rows(
        $conn,
        "SELECT account_no FROM tbl_subs_profile WHERE account_no LIKE ? FOR UPDATE",
        's',
        [$prefix . '%']
    );
    $seq = 0;
    foreach ($taken as $row) {
        $existing = shaven_parse_account_number($row['account_no'] ?? '');
        if ($existing && $existing['year'] === $year) {
            $seq = max($seq, $existing['seq']);
        }
    }
    $number = shaven_format_account_number($year, $seq + 1);
    $saved = shaven_exec(
        $conn,
        "UPDATE tbl_subs_profile SET account_no=? WHERE id=? AND (account_no IS NULL OR account_no='')",
        'si',
        [$number, $subscriberId]
    );
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        mysqli_rollback($conn);
        return '';
    }
    mysqli_commit($conn);
    return $number;
}

function shaven_account_cell($accountNo, $installedOn) {
    $installed = shaven_install_date($installedOn);
    $order = $installed ? $installed->format('Y-m-d') : '9999-12-31';
    $when = $installed ? ('Installed ' . $installed->format('M j, Y')) : 'No installation date';
    $number = trim((string)$accountNo);
    if ($number === '') {
        $number = 'No account number';
    }
    return '<td data-order="' . h($order) . '"><div class="shaven-account-no">' . h($number) . '</div><div class="shaven-account-installed">' . h($when) . '</div></td>';
}

function shaven_payment_medium($value) {
    $value = strtolower(trim((string)$value));
    if ($value === '' || $value === 'cash' || $value === 'counter' || $value === 'office') {
        return 'cash';
    }
    if ($value === 'gcash') {
        return 'gcash';
    }
    return '';
}

function shaven_channel_label($status, $channel) {
    if ((int)$status !== 1) {
        return 'Unpaid';
    }
    $channel = strtolower(trim((string)$channel));
    if ($channel === 'portal') {
        return 'Subscriber portal';
    }
    if ($channel === 'gcash') {
        return 'GCash';
    }
    return 'Cash';
}

function shaven_csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function shaven_csrf_ok() {
    $sent = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    return is_string($sent) && $known !== '' && hash_equals($known, $sent);
}

function shaven_flash_set($message, $type = 'ok', array $receipts = []) {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
        'receipts' => $receipts,
    ];
}

function shaven_flash_html() {
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $class = (($flash['type'] ?? '') === 'err') ? 'alert-danger' : 'alert-success';
    echo '<div class="alert ' . $class . '">' . h($flash['message'] ?? '');
    if (!empty($flash['receipts']) && is_array($flash['receipts'])) {
        echo '<div class="mt-2">';
        $ids = [];
        foreach ($flash['receipts'] as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids) {
            $href = shaven_url('pages/tables/receipt.php?ids=' . implode(',', $ids));
            echo '<a class="btn btn-sm btn-outline-dark mr-2 mb-1" href="' . h($href) . '">Open receipt</a>';
            echo '<a class="btn btn-sm btn-dark mb-1" href="' . h($href . '&download=1') . '">Download image</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function shaven_bind($stmt, $types, array $params) {
    if ($types === '' || !$stmt) {
        return;
    }
    $refs = array_values($params);
    $bind = [];
    $bind[] = &$stmt;
    $bind[] = &$types;
    foreach ($refs as $key => $value) {
        $bind[] = &$refs[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function shaven_rows($conn, $sql, $types = '', array $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    shaven_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [];
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function shaven_exec($conn, $sql, $types = '', array $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'affected' => 0, 'id' => 0, 'error' => mysqli_error($conn)];
    }
    shaven_bind($stmt, $types, $params);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    $id = mysqli_insert_id($conn);
    $error = $ok ? '' : mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    return ['ok' => (bool)$ok, 'affected' => $affected, 'id' => $id, 'error' => $error];
}

function shaven_column_exists($conn, $table, $column) {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = str_replace(['\\', '\'', '%', '_'], '', (string)$column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && mysqli_num_rows($result) > 0;
}

function shaven_migrate($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!shaven_column_exists($conn, 'tbl_sys_users', 'subscriber_id')) {
        mysqli_query($conn, "ALTER TABLE tbl_sys_users ADD subscriber_id INT NULL DEFAULT NULL");
    }
    if (!shaven_column_exists($conn, 'tbl_sys_users', 'photo')) {
        mysqli_query($conn, "ALTER TABLE tbl_sys_users ADD photo VARCHAR(255) NULL DEFAULT NULL");
    }
    if (!shaven_column_exists($conn, 'tbl_payments', 'pay_channel')) {
        mysqli_query($conn, "ALTER TABLE tbl_payments ADD pay_channel VARCHAR(20) NOT NULL DEFAULT 'counter'");
    }
    if (!shaven_column_exists($conn, 'tbl_payments', 'period_start')) {
        mysqli_query($conn, "ALTER TABLE tbl_payments ADD period_start DATE NULL DEFAULT NULL");
    }
    if (!shaven_column_exists($conn, 'tbl_payments', 'period_end')) {
        mysqli_query($conn, "ALTER TABLE tbl_payments ADD period_end DATE NULL DEFAULT NULL");
    }
    if (!shaven_column_exists($conn, 'tbl_payments', 'due_date')) {
        mysqli_query($conn, "ALTER TABLE tbl_payments ADD due_date DATE NULL DEFAULT NULL");
    }
    mysqli_query(
        $conn,
        "UPDATE tbl_payments
         SET due_date = DATE_ADD(period_end, INTERVAL 1 DAY)
         WHERE period_end IS NOT NULL
           AND period_end > '1970-01-01'
           AND (due_date IS NULL OR due_date <> DATE_ADD(period_end, INTERVAL 1 DAY))"
    );
    shaven_migrate_billing_month_from_due($conn);
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_plan_changes (
        id INT NOT NULL AUTO_INCREMENT,
        subscriber_id INT NOT NULL,
        old_plan_id INT NULL,
        new_plan_id INT NOT NULL,
        changed_by INT NOT NULL,
        note VARCHAR(255) NULL,
        changed_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_admin_level (
        id INT NOT NULL AUTO_INCREMENT,
        admin_lvl VARCHAR(255) NULL DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "INSERT INTO tbl_admin_level (id, admin_lvl) VALUES
        (1, 'Owner'),
        (2, 'Admin'),
        (3, 'Technician'),
        (4, 'Subscriber')
        ON DUPLICATE KEY UPDATE admin_lvl = VALUES(admin_lvl)");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_finances (
        id INT NOT NULL AUTO_INCREMENT,
        dateoftransaction DATETIME NULL DEFAULT NULL,
        description VARCHAR(255) NULL DEFAULT NULL,
        debit DOUBLE NOT NULL DEFAULT 0,
        credit DOUBLE NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $profile = shaven_rows($conn, "SELECT id, s_name FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [27]);
    if ($profile) {
        $name = $profile[0]['s_name'];
        shaven_exec(
            $conn,
            "UPDATE tbl_sys_users
             SET sys_level=4, subscriber_id=?, sys_user_fullname=?
             WHERE username='user' AND sys_user_fullname='Deactivated Time' AND (subscriber_id IS NULL OR subscriber_id=0)",
            'is',
            [27, $name]
        );
    }
    shaven_exec(
        $conn,
        "UPDATE tbl_sys_users SET sys_level=4 WHERE username='user' AND sys_user_fullname='Deactivated Time' AND sys_level=3"
    );

    if (!shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE username=? LIMIT 1", 's', ['technician'])) {
        shaven_exec(
            $conn,
            "INSERT INTO tbl_sys_users (username, password, sys_user_fullname, sys_user_dor, sys_level, status)
             VALUES ('technician', 'technician', 'Field Technician', NOW(), 3, 1)"
        );
    }
    if (!shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE username=? LIMIT 1", 's', ['adminstaff'])) {
        shaven_exec(
            $conn,
            "INSERT INTO tbl_sys_users (username, password, sys_user_fullname, sys_user_dor, sys_level, status)
             VALUES ('adminstaff', 'adminstaff', 'Office Admin', NOW(), 2, 1)"
        );
    }
    $batchColumn = mysqli_query($conn, "SHOW COLUMNS FROM tbl_remittances LIKE 'batch_id'");
    if (!$batchColumn || mysqli_num_rows($batchColumn) === 0) {
        mysqli_query($conn, "ALTER TABLE tbl_remittances ADD batch_id INT NULL DEFAULT NULL");
    }
    $accountColumn = mysqli_query($conn, "SHOW COLUMNS FROM tbl_subs_profile LIKE 'account_no'");
    if (!$accountColumn || mysqli_num_rows($accountColumn) === 0) {
        mysqli_query($conn, "ALTER TABLE tbl_subs_profile ADD account_no VARCHAR(32) NULL DEFAULT NULL");
    }
    shaven_assign_missing_account_numbers($conn);
    $accountIndex = mysqli_query($conn, "SHOW INDEX FROM tbl_subs_profile WHERE Key_name='account_no'");
    if (!$accountIndex || mysqli_num_rows($accountIndex) === 0) {
        mysqli_query($conn, "ALTER TABLE tbl_subs_profile ADD UNIQUE KEY account_no (account_no)");
    }
    $revertBy = mysqli_query($conn, "SHOW COLUMNS FROM tbl_payments LIKE 'revert_by'");
    if (!$revertBy || mysqli_num_rows($revertBy) === 0) {
        mysqli_query($conn, "ALTER TABLE tbl_payments ADD revert_by INT NULL DEFAULT NULL");
    }
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_remit_batches (
        id INT NOT NULL AUTO_INCREMENT,
        ref VARCHAR(40) NOT NULL,
        collector_id INT NOT NULL,
        submitted_at DATETIME NOT NULL,
        amount DOUBLE NOT NULL DEFAULT 0,
        item_count INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        confirmed_by INT NULL,
        confirmed_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY collector_id (collector_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    shaven_notify_install($conn);
    shaven_ledger_install($conn);
    shaven_theme_install($conn);
}

function shaven_refresh_user($conn) {
    if (empty($_SESSION['usr_id'])) {
        return;
    }
    $rows = shaven_rows(
        $conn,
        "SELECT id, sys_user_fullname, sys_level, status, subscriber_id, photo FROM tbl_sys_users WHERE id=? LIMIT 1",
        'i',
        [(int)$_SESSION['usr_id']]
    );
    $row = $rows[0] ?? null;
    if (!$row || (int)$row['status'] !== 1) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if (shaven_rel_script() !== '/login.php') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            shaven_redirect('/login.php');
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return;
    }
    $_SESSION['usr_id'] = (int)$row['id'];
    $_SESSION['fullname'] = $row['sys_user_fullname'];
    $_SESSION['photo'] = (string)($row['photo'] ?? '');
    $_SESSION['sys_level'] = (int)$row['sys_level'];
    $_SESSION['subscriber_id'] = (int)($row['subscriber_id'] ?? 0);
}

function shaven_path_starts($rel, $prefix) {
    return strncmp($rel, $prefix, strlen($prefix)) === 0;
}

function shaven_role_allowed($role, $rel) {
    if ($role === 1 || $role === 2) {
        return true;
    }
    if ($role === 4) {
        return $rel === '/index.php'
            || $rel === '/logout.php'
            || shaven_path_starts($rel, '/pages/account/')
            || $rel === '/pages/tables/or.php'
            || $rel === '/pages/tables/receipt.php';
    }
    $techDeny = [
        '/pages/admin/',
        '/pages/account/',
        '/pages/forms/',
        '/pages/tables/updatebill',
        '/pages/tables/allsubs',
        '/pages/tables/collectibles',
        '/pages/tables/manual_entry',
        '/pages/tables/collections_revert_approval.php',
        '/pages/tables/collections_posted.php',
        '/pages/tables/collections_paid.php',
        '/pages/tables/collections_monthly.php',
        '/pages/tables/collections_15d.php',
        '/pages/tables/my_ledger.php',
        '/pages/tables/addtoremittance.php',
    ];
    if ($role !== 3) {
        return false;
    }
    $deny = $techDeny;
    if ($rel === '/pages/account/profile.php' || $rel === '/pages/account/notifications.php') {
        return true;
    }
    foreach ($deny as $prefix) {
        if (shaven_path_starts($rel, $prefix)) {
            return false;
        }
    }
    return true;
}

function shaven_enforce() {
    $rel = shaven_rel_script();
    if ($rel === '/pages/tables/cron.php') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return;
        }
        shaven_redirect('/login.php');
    }
    if (shaven_path_starts($rel, '/includes/')) {
        http_response_code(404);
        exit;
    }
    $public = ['/login.php', '/logout.php'];
    if (in_array($rel, $public, true)) {
        return;
    }
    if (empty($_SESSION['usr_id'])) {
        shaven_redirect('/login.php');
    }
    if (!shaven_role_allowed(shaven_role(), $rel)) {
        shaven_redirect('/index.php?denied=1');
    }
}

function shaven_web_boot($enforce = true) {
    static $started = false;
    if (!$started) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            require SHAVEN_ROOT . '/dbconnect.php';
        }
        shaven_migrate($conn);
        shaven_refresh_user($conn);
        shaven_notify_refresh($conn);
        $started = true;
    }
    if ($enforce) {
        shaven_enforce();
    }
}

function shaven_receipt_number() {
    return 'SN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function shaven_can_view_receipt($conn, $paymentId) {
    $paymentId = (int)$paymentId;
    if ($paymentId <= 0) {
        return false;
    }
    if (shaven_role_is(1, 2)) {
        return true;
    }
    if (shaven_role_is(3)) {
        $rows = shaven_rows($conn, "SELECT postedby FROM tbl_payments WHERE id=? LIMIT 1", 'i', [$paymentId]);
        return $rows && (int)$rows[0]['postedby'] === (int)($_SESSION['usr_id'] ?? 0);
    }
    if (!shaven_role_is(4)) {
        return false;
    }
    $rows = shaven_rows($conn, "SELECT payment_uid FROM tbl_payments WHERE id=? LIMIT 1", 'i', [$paymentId]);
    return $rows && (int)$rows[0]['payment_uid'] === (int)($_SESSION['subscriber_id'] ?? 0);
}

function shaven_month_number($name) {
    static $map = null;
    if ($map === null) {
        $map = [];
        for ($month = 1; $month <= 12; $month++) {
            $map[strtolower(shaven_month_name($month))] = $month;
        }
    }
    return $map[strtolower(trim((string)$name))] ?? 0;
}

function shaven_migrate_billing_month_from_due($conn) {
    $needs = shaven_rows(
        $conn,
        "SELECT id FROM tbl_payments
         WHERE (
                (due_date IS NOT NULL AND due_date > '1970-01-01')
             OR (period_end IS NOT NULL AND period_end > '1970-01-01')
         )
           AND (
                due_date IS NULL
             OR payment_year <> YEAR(due_date)
             OR payment_day <> DAY(due_date)
             OR LOWER(TRIM(payment_month)) <> LOWER(MONTHNAME(due_date))
           )
         LIMIT 1"
    );
    if (!$needs) {
        return;
    }
    $rows = shaven_rows(
        $conn,
        "SELECT id, payment_uid, payment_year, payment_month, payment_day, due_date, period_end
         FROM tbl_payments
         WHERE (due_date IS NOT NULL AND due_date > '1970-01-01')
            OR (period_end IS NOT NULL AND period_end > '1970-01-01')"
    );
    if (!$rows) {
        return;
    }
    $occupied = [];
    foreach ($rows as $row) {
        $uid = (int)$row['payment_uid'];
        $month = strtolower(trim((string)$row['payment_month']));
        $year = (int)$row['payment_year'];
        if ($month === '' || $year < 1) {
            continue;
        }
        $occupied[$uid][$year . '|' . $month] = (int)$row['id'];
    }
    foreach ($rows as $row) {
        $due = substr((string)($row['due_date'] ?? ''), 0, 10);
        if ($due === '' || strpos($due, '0000-00-00') === 0) {
            $due = shaven_due_after_period($row['period_end'] ?? '');
        }
        $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
        if (!$dueDate) {
            continue;
        }
        $newYear = (int)$dueDate->format('Y');
        $newMonth = shaven_month_name((int)$dueDate->format('n'));
        $newDay = (int)$dueDate->format('j');
        $oldYear = (int)$row['payment_year'];
        $oldMonth = trim((string)$row['payment_month']);
        $oldDay = (int)$row['payment_day'];
        if ($oldYear === $newYear && strcasecmp($oldMonth, $newMonth) === 0 && $oldDay === $newDay) {
            continue;
        }
        $uid = (int)$row['payment_uid'];
        $id = (int)$row['id'];
        $newKey = $newYear . '|' . strtolower($newMonth);
        if (isset($occupied[$uid][$newKey]) && $occupied[$uid][$newKey] !== $id) {
            continue;
        }
        $saved = shaven_exec(
            $conn,
            "UPDATE tbl_payments SET payment_year=?, payment_month=?, payment_day=? WHERE id=?",
            'isii',
            [$newYear, $newMonth, $newDay, $id]
        );
        if (!$saved['ok']) {
            continue;
        }
        $oldKey = $oldYear . '|' . strtolower($oldMonth);
        if (isset($occupied[$uid][$oldKey]) && $occupied[$uid][$oldKey] === $id) {
            unset($occupied[$uid][$oldKey]);
        }
        $occupied[$uid][$newKey] = $id;
    }
}

function shaven_due_after_period($periodEnd) {
    if ($periodEnd instanceof DateTimeImmutable) {
        return $periodEnd->modify('+1 day')->format('Y-m-d');
    }
    $raw = substr(trim((string)$periodEnd), 0, 10);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$end || ($errors && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0))) {
        return '';
    }
    return $end->modify('+1 day')->format('Y-m-d');
}

function shaven_bill_period(array $bill, $installedOn) {
    $storedStart = substr((string)($bill['period_start'] ?? ''), 0, 10);
    $storedEnd = substr((string)($bill['period_end'] ?? ''), 0, 10);
    $storedDue = substr((string)($bill['due_date'] ?? ''), 0, 10);
    if ($storedStart !== '' && $storedEnd !== '' && strpos($storedStart, '0000-00-00') !== 0 && strpos($storedEnd, '0000-00-00') !== 0) {
        $due = ($storedDue !== '' && strpos($storedDue, '0000-00-00') !== 0)
            ? $storedDue
            : shaven_due_after_period($storedEnd);
        return [
            'start' => $storedStart,
            'end' => $storedEnd,
            'due' => $due,
        ];
    }
    $installed = shaven_install_date($installedOn);
    $month = shaven_month_number($bill['payment_month'] ?? '');
    $year = (int)($bill['payment_year'] ?? 0);
    if (!$installed || $month < 1 || $year < 1990) {
        return null;
    }
    $target = ($year * 100) + $month;
    for ($offset = 0; $offset < 600; $offset++) {
        $start = shaven_billing_anchor($installed, $offset);
        $end = shaven_billing_anchor($installed, $offset + 1)->modify('-1 day');
        $due = shaven_due_after_period($end);
        $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
        if (!$dueDate) {
            continue;
        }
        $ym = ((int)$dueDate->format('Y') * 100) + (int)$dueDate->format('n');
        if ($ym === $target) {
            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'due' => $due,
            ];
        }
        if ($ym > $target) {
            break;
        }
    }
    return null;
}

function shaven_due_sort_value(array $bill, $installedOn = null) {
    $period = shaven_bill_period($bill, $installedOn ?? ($bill['s_doi'] ?? ''));
    return (!empty($period['due'])) ? $period['due'] : '9999-12-31';
}

function shaven_sort_by_due(array &$rows, $installedOn = null) {
    usort($rows, function ($left, $right) use ($installedOn) {
        $leftInstall = $installedOn !== null ? $installedOn : ($left['s_doi'] ?? '');
        $rightInstall = $installedOn !== null ? $installedOn : ($right['s_doi'] ?? '');
        $byDue = strcmp(shaven_due_sort_value($left, $leftInstall), shaven_due_sort_value($right, $rightInstall));
        if ($byDue !== 0) {
            return $byDue;
        }
        return strcmp((string)($left['s_name'] ?? $left['name'] ?? ''), (string)($right['s_name'] ?? $right['name'] ?? ''));
    });
}

function shaven_due_text(array $bill, $installedOn = null) {
    $period = shaven_bill_period($bill, $installedOn ?? ($bill['s_doi'] ?? ''));
    if (!$period || empty($period['due'])) {
        return '';
    }
    return shaven_date($period['due'], 'F j, Y');
}

function shaven_due_cell(array $bill, $installedOn = null) {
    $period = shaven_bill_period($bill, $installedOn ?? ($bill['s_doi'] ?? ''));
    if (!$period || empty($period['due'])) {
        return '<td data-order="9999-12-31"><span class="shaven-due-date">No due date</span></td>';
    }
    return '<td data-order="' . h($period['due']) . '"><span class="shaven-due-date">' . h(shaven_date($period['due'], 'F j, Y')) . '</span></td>';
}

function shaven_billing_month_label(array $bill) {
    $month = trim((string)($bill['payment_month'] ?? $bill['month'] ?? ''));
    $year = (int)($bill['payment_year'] ?? $bill['year'] ?? 0);
    if ($month !== '' && $year > 0) {
        return $month . ' ' . $year;
    }
    if ($month !== '') {
        return $month;
    }
    if ($year > 0) {
        return (string)$year;
    }
    return '—';
}

function shaven_billing_month_order(array $bill) {
    $month = trim((string)($bill['payment_month'] ?? $bill['month'] ?? ''));
    $year = (int)($bill['payment_year'] ?? $bill['year'] ?? 0);
    $number = shaven_month_number($month);
    if ($year > 0 && $number > 0) {
        return sprintf('%04d-%02d', $year, $number);
    }
    return '9999-99';
}

function shaven_billing_month_cell(array $bill) {
    return '<td data-order="' . h(shaven_billing_month_order($bill)) . '">' . h(shaven_billing_month_label($bill)) . '</td>';
}

function shaven_bill_period_label(array $bill, $installedOn) {
    $period = shaven_bill_period($bill, $installedOn);
    if (!$period) {
        $label = shaven_billing_month_label($bill);
        return $label !== '—' ? $label : 'Billing month not on file';
    }
    return shaven_date($period['start'], 'F j, Y') . ' through ' . shaven_date($period['end'], 'F j, Y');
}

function shaven_month_name($month) {
    $names = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    return $names[(int)$month] ?? '';
}

function shaven_install_date($value) {
    $raw = substr(trim((string)$value), 0, 10);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return null;
    }
    $installed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$installed || ($errors && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0))) {
        return null;
    }
    return $installed;
}

function shaven_billing_anchor(DateTimeImmutable $installed, $offset) {
    $day = (int)$installed->format('j');
    $cursor = $installed->modify('first day of this month')->modify('+' . (int)$offset . ' months');
    $last = (int)$cursor->format('t');
    return $cursor->setDate((int)$cursor->format('Y'), (int)$cursor->format('n'), min($day, $last));
}

function shaven_installation_periods($installedOn, $fromYm, $toYm, DateTimeImmutable $today = null) {
    $installed = $installedOn instanceof DateTimeImmutable ? $installedOn : shaven_install_date($installedOn);
    if (!$installed) {
        return [];
    }
    $today = $today ? $today->setTime(0, 0) : new DateTimeImmutable('today');
    $periods = [];
    for ($offset = 0; $offset < 600; $offset++) {
        $start = shaven_billing_anchor($installed, $offset);
        if ($start > $today) {
            break;
        }
        $next = shaven_billing_anchor($installed, $offset + 1);
        $end = $next->modify('-1 day');
        $due = shaven_due_after_period($end);
        $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
        if (!$dueDate) {
            continue;
        }
        $ym = ((int)$dueDate->format('Y') * 100) + (int)$dueDate->format('n');
        if ($fromYm !== null && ($ym < (int)$fromYm || $ym > (int)$toYm)) {
            continue;
        }
        $periods[] = [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'due' => $due,
            'year' => (int)$dueDate->format('Y'),
            'month' => shaven_month_name((int)$dueDate->format('n')),
            'day' => (int)$dueDate->format('j'),
        ];
    }
    return $periods;
}

function shaven_billing_range($fromYear, $fromMonth, $toYear, $toMonth) {
    $fromYear = (int)$fromYear;
    $toYear = (int)$toYear;
    $fromMonth = (int)$fromMonth;
    $toMonth = (int)$toMonth;
    if ($fromYear === 0 && $toYear === 0) {
        return ['ok' => true, 'from' => null, 'to' => null, 'label' => 'every missing billing month from installation through the current month'];
    }
    if ($fromYear === 0 || $toYear === 0) {
        return ['ok' => false, 'error' => 'Enter both the starting year and the ending year, or leave both years empty.'];
    }
    if ($fromYear < 1990 || $toYear > 2100 || $fromMonth < 1 || $fromMonth > 12 || $toMonth < 1 || $toMonth > 12) {
        return ['ok' => false, 'error' => 'Choose a real year and month for both ends of the range.'];
    }
    $from = ($fromYear * 100) + $fromMonth;
    $to = ($toYear * 100) + $toMonth;
    if ($from > $to) {
        return ['ok' => false, 'error' => 'The starting billing month is later than the ending billing month.'];
    }
    $label = shaven_month_name($fromMonth) . ' ' . $fromYear . ' through ' . shaven_month_name($toMonth) . ' ' . $toYear;
    return ['ok' => true, 'from' => $from, 'to' => $to, 'label' => $label];
}

function shaven_billing_half_label($half) {
    $half = (int)$half;
    if ($half === 1) {
        return '1st half (due on days 1–15)';
    }
    if ($half === 2) {
        return '2nd half (due on days 16–31)';
    }
    return 'whole month';
}

function shaven_billing_plan($conn, $fromYear, $fromMonth, $toYear, $toMonth, $subscriberId = 0, $half = 0) {
    if (!shaven_role_is(1, 2)) {
        return ['ok' => false, 'error' => 'Bills are generated by an admin or the owner.'];
    }
    $range = shaven_billing_range($fromYear, $fromMonth, $toYear, $toMonth);
    if (!$range['ok']) {
        return $range;
    }
    $half = (int)$half;
    if ($half !== 1 && $half !== 2) {
        $half = 0;
    }
    if ($half > 0) {
        $range['label'] .= ', ' . shaven_billing_half_label($half);
    }
    $subscriberId = (int)$subscriberId;
    $where = 's.status=1';
    $types = '';
    $params = [];
    if ($subscriberId > 0) {
        $where .= ' AND s.id=?';
        $types = 'i';
        $params[] = $subscriberId;
    }
    $subscribers = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_doi, pl.p_monthly_amount
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
         WHERE {$where}
         ORDER BY s.s_doi, s.s_name",
        $types,
        $params
    );
    if ($subscriberId > 0 && !$subscribers) {
        return ['ok' => false, 'error' => 'Choose an active subscriber. Inactive lines are not billed from this page.'];
    }
    $existingRows = shaven_rows($conn, "SELECT payment_uid, payment_year, payment_month FROM tbl_payments");
    $existing = [];
    foreach ($existingRows as $row) {
        $month = trim((string)$row['payment_month']);
        if ($month === '') {
            continue;
        }
        $existing[(int)$row['payment_uid'] . '|' . (int)$row['payment_year'] . '|' . strtolower($month)] = true;
    }
    $create = [];
    $skipped = 0;
    $noDate = 0;
    $noAmount = 0;
    foreach ($subscribers as $subscriber) {
        $installed = shaven_install_date($subscriber['s_doi']);
        if (!$installed) {
            $noDate++;
            continue;
        }
        $amount = round((float)$subscriber['p_monthly_amount'], 2);
        if ($amount <= 0) {
            $noAmount++;
            continue;
        }
        foreach (shaven_installation_periods($installed, $range['from'], $range['to']) as $period) {
            if (($half === 1 && (int)$period['day'] > 15) || ($half === 2 && (int)$period['day'] < 16)) {
                continue;
            }
            $key = (int)$subscriber['id'] . '|' . $period['year'] . '|' . strtolower($period['month']);
            if (isset($existing[$key])) {
                $skipped++;
                continue;
            }
            $create[] = [
                'subscriber_id' => (int)$subscriber['id'],
                'name' => $subscriber['s_name'],
                'installed' => $installed->format('Y-m-d'),
                'amount' => $amount,
                'start' => $period['start'],
                'end' => $period['end'],
                'due' => $period['due'],
                'year' => $period['year'],
                'month' => $period['month'],
                'day' => $period['day'],
            ];
        }
    }
    usort($create, function ($left, $right) {
        return strcasecmp($left['name'], $right['name']) ?: strcmp($left['due'], $right['due']);
    });
    return [
        'ok' => true,
        'error' => '',
        'label' => ($subscriberId > 0 ? $subscribers[0]['s_name'] . ', ' : '') . $range['label'],
        'active' => count($subscribers),
        'skipped' => $skipped,
        'no_date' => $noDate,
        'no_amount' => $noAmount,
        'subscriber' => $subscriberId > 0 ? (string)$subscribers[0]['s_name'] : '',
        'create' => $create,
    ];
}

function shaven_billing_generate($conn, $fromYear, $fromMonth, $toYear, $toMonth, $subscriberId = 0, $half = 0) {
    $plan = shaven_billing_plan($conn, $fromYear, $fromMonth, $toYear, $toMonth, $subscriberId, $half);
    if (!$plan['ok']) {
        return $plan;
    }
    $subscriberName = (string)($plan['subscriber'] ?? '');
    if (!$plan['create']) {
        return ['ok' => true, 'error' => '', 'created' => 0, 'skipped' => (int)$plan['skipped'], 'subscriber' => $subscriberName];
    }
    mysqli_begin_transaction($conn);
    $created = 0;
    $bySubscriber = [];
    foreach ($plan['create'] as $bill) {
        $saved = shaven_exec(
            $conn,
            "INSERT INTO tbl_payments
                (payment_year, payment_amount, payment_day, payment_month, payment_uid, p_timestamp, rf, pay_channel, period_start, period_end, due_date)
             VALUES (?, ?, ?, ?, ?, NOW(), '', 'bill', ?, ?, ?)",
            'idisisss',
            [$bill['year'], $bill['amount'], $bill['day'], $bill['month'], $bill['subscriber_id'], $bill['start'], $bill['end'], $bill['due']]
        );
        if (!$saved['ok']) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'The bills were not created. Nothing from this run was saved.'];
        }
        $created++;
        $subId = (int)$bill['subscriber_id'];
        if (!isset($bySubscriber[$subId])) {
            $bySubscriber[$subId] = ['name' => (string)$bill['name'], 'count' => 0];
        }
        $bySubscriber[$subId]['count']++;
    }
    mysqli_commit($conn);
    shaven_notify_bills_created($conn, $bySubscriber, $created);
    return ['ok' => true, 'error' => '', 'created' => $created, 'skipped' => (int)$plan['skipped'], 'subscriber' => $subscriberName];
}

function shaven_subscriber_account($conn, $subscriberId) {
    $subscriberId = (int)$subscriberId;
    if ($subscriberId <= 0) {
        return null;
    }
    $rows = shaven_rows(
        $conn,
        "SELECT s.id, s.account_no, s.s_name, s.s_address, s.s_doi, s.timestamp, s.status, s.plan_id, s.medium,
                pl.p_name, pl.p_speed_kb, pl.p_monthly_amount, md.medium_name
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
         LEFT JOIN tbl_mediums md ON md.id = s.medium
         WHERE s.id=? LIMIT 1",
        'i',
        [$subscriberId]
    );
    if (!$rows) {
        return null;
    }
    $profile = $rows[0];
    $year = (int)date('Y');
    $totals = shaven_rows(
        $conn,
        "SELECT
            COALESCE(SUM(CASE WHEN status=1 THEN payment_amount END),0) lifetime_paid,
            COALESCE(SUM(CASE WHEN status=1 AND payment_year=? THEN payment_amount END),0) year_paid,
            COALESCE(SUM(CASE WHEN status=0 THEN payment_amount END),0) balance,
            COALESCE(SUM(CASE WHEN status=0 THEN 1 END),0) open_count,
            COALESCE(SUM(CASE WHEN status=1 AND payment_year=? THEN 1 END),0) year_paid_count
         FROM tbl_payments WHERE payment_uid=?",
        'iii',
        [$year, $year, $subscriberId]
    );
    $open = shaven_rows(
        $conn,
        "SELECT p.id, p.status, p.payment_month, p.payment_year, p.payment_day, p.payment_amount, p.p_timestamp,
                p.period_start, p.period_end, p.due_date
         FROM tbl_payments p
         LEFT JOIN tbl_months m ON m.month_name = p.payment_month
         WHERE p.payment_uid=? AND p.status=0
         ORDER BY p.payment_year, m.id, p.id",
        'i',
        [$subscriberId]
    );
    $history = shaven_rows(
        $conn,
        "SELECT p.id, p.payment_date, p.payment_month, p.payment_year, p.payment_day, p.payment_amount,
                p.rf, p.pay_channel, p.status, p.period_start, p.period_end, p.due_date,
                u.sys_user_fullname
         FROM tbl_payments p
         LEFT JOIN tbl_months m ON m.month_name = p.payment_month
         LEFT JOIN tbl_sys_users u ON u.id = p.postedby
         WHERE p.payment_uid=? AND p.status=1
         ORDER BY p.payment_year DESC, m.id DESC, p.payment_date DESC, p.id DESC",
        'i',
        [$subscriberId]
    );
    shaven_sort_by_due($open, $profile['s_doi']);
    shaven_sort_by_due($history, $profile['s_doi']);
    $history = array_reverse($history);
    $changes = shaven_rows(
        $conn,
        "SELECT c.changed_at, c.note, c.old_plan_id, c.new_plan_id,
                op.p_name AS old_name, np.p_name AS new_name, np.p_monthly_amount AS new_amount,
                u.sys_user_fullname
         FROM tbl_plan_changes c
         LEFT JOIN tbl_plans op ON op.id = c.old_plan_id
         LEFT JOIN tbl_plans np ON np.id = c.new_plan_id
         LEFT JOIN tbl_sys_users u ON u.id = c.changed_by
         WHERE c.subscriber_id=?
         ORDER BY c.id DESC
         LIMIT 20",
        'i',
        [$subscriberId]
    );
    $portal = shaven_rows(
        $conn,
        "SELECT id, username, status FROM tbl_sys_users WHERE subscriber_id=? AND sys_level=4 ORDER BY id LIMIT 1",
        'i',
        [$subscriberId]
    );
    $stat = $totals[0] ?? [];
    return [
        'profile' => $profile,
        'lifetime_paid' => (float)($stat['lifetime_paid'] ?? 0),
        'year_paid' => (float)($stat['year_paid'] ?? 0),
        'year_paid_count' => (int)($stat['year_paid_count'] ?? 0),
        'balance' => (float)($stat['balance'] ?? 0),
        'open_count' => (int)($stat['open_count'] ?? 0),
        'open_bills' => $open,
        'history' => $history,
        'plan_changes' => $changes,
        'portal' => $portal[0] ?? null,
        'year' => $year,
    ];
}

function shaven_change_plan($conn, $subscriberId, $newPlanId, $userId, $note) {
    $subscriberId = (int)$subscriberId;
    $newPlanId = (int)$newPlanId;
    $userId = (int)$userId;
    if (!shaven_role_is(1, 2, 4)) {
        return 'Your role can view plans. Plan changes are handled by the subscriber, admin, or owner.';
    }
    if (shaven_role() === 4 && (int)($_SESSION['subscriber_id'] ?? 0) !== $subscriberId) {
        return 'Plan changes apply to your own subscriber account.';
    }
    $subs = shaven_rows($conn, "SELECT id, plan_id FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
    if (!$subs) {
        return 'That subscriber account was not found.';
    }
    $plans = shaven_rows($conn, "SELECT id, p_monthly_amount, p_name FROM tbl_plans WHERE id=? LIMIT 1", 'i', [$newPlanId]);
    if (!$plans) {
        return 'Choose a plan from the list.';
    }
    $oldPlanId = (int)$subs[0]['plan_id'];
    if ($oldPlanId === $newPlanId) {
        return 'This account is already on ' . $plans[0]['p_name'] . '.';
    }
    $oldName = 'the previous plan';
    if ($oldPlanId > 0) {
        $oldPlans = shaven_rows($conn, "SELECT p_name FROM tbl_plans WHERE id=? LIMIT 1", 'i', [$oldPlanId]);
        if ($oldPlans && trim((string)$oldPlans[0]['p_name']) !== '') {
            $oldName = $oldPlans[0]['p_name'];
        }
    }
    $note = substr(trim((string)$note), 0, 255);
    $month = date('F');
    $year = (int)date('Y');
    $amount = (float)$plans[0]['p_monthly_amount'];
    mysqli_begin_transaction($conn);
    $profile = shaven_exec($conn, "UPDATE tbl_subs_profile SET plan_id=? WHERE id=?", 'ii', [$newPlanId, $subscriberId]);
    $bill = shaven_exec(
        $conn,
        "UPDATE tbl_payments SET payment_amount=? WHERE payment_uid=? AND status=0 AND payment_year=? AND payment_month=?",
        'diis',
        [$amount, $subscriberId, $year, $month]
    );
    $log = shaven_exec(
        $conn,
        "INSERT INTO tbl_plan_changes (subscriber_id, old_plan_id, new_plan_id, changed_by, note, changed_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        'iiiis',
        [$subscriberId, $oldPlanId, $newPlanId, $userId, $note]
    );
    if (!$profile['ok'] || !$bill['ok'] || !$log['ok']) {
        mysqli_rollback($conn);
        return 'The plan change did not save. Try it again.';
    }
    mysqli_commit($conn);
    shaven_notify_plan_changed($conn, $subscriberId, $userId, $oldName, $plans[0]['p_name']);
    return true;
}

function shaven_plan_catalog($conn) {
    return shaven_rows(
        $conn,
        "SELECT pl.id, pl.p_name, pl.p_speed_kb, pl.p_monthly_amount,
                (SELECT COUNT(*) FROM tbl_subs_profile s WHERE s.plan_id = pl.id) AS subscribers
         FROM tbl_plans pl
         ORDER BY pl.p_monthly_amount, pl.id"
    );
}

function shaven_save_plan($conn, $id, $name, $mbps, $amount) {
    if (!shaven_role_is(1, 2)) {
        return ['ok' => false, 'error' => 'Plan details are changed by an admin or the owner.', 'bills' => 0];
    }
    $id = (int)$id;
    $name = substr(trim((string)$name), 0, 255);
    $mbps = (float)$mbps;
    $amount = round((float)$amount, 2);
    if ($name === '' || $mbps <= 0 || $amount <= 0) {
        return ['ok' => false, 'error' => 'Enter a plan name, a speed, and a monthly amount.', 'bills' => 0];
    }
    $kb = (int)round($mbps * 1000);
    if ($id > 0) {
        $current = shaven_rows($conn, "SELECT id, p_monthly_amount FROM tbl_plans WHERE id=? LIMIT 1", 'i', [$id]);
        if (!$current) {
            return ['ok' => false, 'error' => 'That plan is no longer in the catalog.', 'bills' => 0];
        }
        $saved = shaven_exec(
            $conn,
            "UPDATE tbl_plans SET p_name=?, p_speed_kb=?, p_monthly_amount=? WHERE id=?",
            'sidi',
            [$name, $kb, $amount, $id]
        );
        if (!$saved['ok']) {
            return ['ok' => false, 'error' => 'The plan was not saved.', 'bills' => 0];
        }
        $bills = 0;
        if (round((float)$current[0]['p_monthly_amount'], 2) !== $amount) {
            $synced = shaven_exec(
                $conn,
                "UPDATE tbl_payments p
                 INNER JOIN tbl_subs_profile s ON s.id = p.payment_uid
                 SET p.payment_amount=?
                 WHERE s.plan_id=? AND p.status=0 AND p.payment_year=? AND p.payment_month=?",
                'diis',
                [$amount, $id, (int)date('Y'), date('F')]
            );
            $bills = $synced['ok'] ? (int)$synced['affected'] : 0;
        }
        return ['ok' => true, 'error' => '', 'bills' => $bills, 'id' => $id];
    }
    $saved = shaven_exec(
        $conn,
        "INSERT INTO tbl_plans (p_name, p_speed_kb, p_monthly_amount) VALUES (?, ?, ?)",
        'sid',
        [$name, $kb, $amount]
    );
    if (!$saved['ok']) {
        return ['ok' => false, 'error' => 'The plan was not added.', 'bills' => 0];
    }
    return ['ok' => true, 'error' => '', 'bills' => 0, 'id' => (int)$saved['id']];
}

function shaven_delete_plan($conn, $id) {
    if (!shaven_role_is(1, 2)) {
        return ['ok' => false, 'error' => 'Plan details are changed by an admin or the owner.'];
    }
    $id = (int)$id;
    $rows = shaven_rows($conn, "SELECT COUNT(*) c FROM tbl_subs_profile WHERE plan_id=?", 'i', [$id]);
    if ((int)($rows[0]['c'] ?? 0) > 0) {
        return ['ok' => false, 'error' => 'Move every subscriber off this plan before removing it.'];
    }
    $saved = shaven_exec($conn, "DELETE FROM tbl_plans WHERE id=?", 'i', [$id]);
    if (!$saved['ok'] || (int)$saved['affected'] !== 1) {
        return ['ok' => false, 'error' => 'The plan was not removed.'];
    }
    return ['ok' => true, 'error' => ''];
}

function shaven_pay_bills($conn, $subscriberId, array $billIds, $userId) {
    $subscriberId = (int)$subscriberId;
    $userId = (int)$userId;
    $ids = [];
    foreach ($billIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (!$ids) {
        return ['ok' => false, 'error' => 'Select at least one open bill.', 'receipts' => []];
    }
    $stamp = date('Y-m-d H:i:s');
    $receipts = [];
    mysqli_begin_transaction($conn);
    foreach ($ids as $id) {
        $receipt = shaven_receipt_number();
        $result = shaven_exec(
            $conn,
            "UPDATE tbl_payments
             SET status=1, postedby=?, payment_date=?, rf=?, pay_channel='portal'
             WHERE id=? AND payment_uid=? AND status=0",
            'issii',
            [$userId, $stamp, $receipt, $id, $subscriberId]
        );
        if (!$result['ok'] || (int)$result['affected'] !== 1) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'One of those bills is already paid or belongs to another account.', 'receipts' => []];
        }
        $receipts[] = $id;
    }
    mysqli_commit($conn);
    shaven_notify_payment_posted($conn, $subscriberId, $userId, $receipts, true);
    return ['ok' => true, 'error' => '', 'receipts' => $receipts];
}

function shaven_anchor_offset(DateTimeImmutable $installed, $startOn) {
    $startOn = substr((string)$startOn, 0, 10);
    for ($offset = 0; $offset < 600; $offset++) {
        $anchor = shaven_billing_anchor($installed, $offset)->format('Y-m-d');
        if ($anchor === $startOn) {
            return $offset;
        }
        if ($anchor > $startOn) {
            return null;
        }
    }
    return null;
}

function shaven_period_at_offset(DateTimeImmutable $installed, $offset) {
    $offset = (int)$offset;
    $start = shaven_billing_anchor($installed, $offset);
    $end = shaven_billing_anchor($installed, $offset + 1)->modify('-1 day');
    $due = shaven_due_after_period($end);
    $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
    if (!$dueDate) {
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'due' => $due,
            'year' => 0,
            'month' => '',
            'day' => 0,
        ];
    }
    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'due' => $due,
        'year' => (int)$dueDate->format('Y'),
        'month' => shaven_month_name((int)$dueDate->format('n')),
        'day' => (int)$dueDate->format('j'),
    ];
}

function shaven_advance_periods(DateTimeImmutable $installed, array $bills, $count) {
    $count = (int)$count;
    if ($count < 1) {
        return [];
    }
    $keys = [];
    $latestOffset = null;
    $latestEnd = null;
    foreach ($bills as $bill) {
        $month = strtolower(trim((string)($bill['payment_month'] ?? '')));
        $year = (int)($bill['payment_year'] ?? 0);
        if ($month !== '' && $year > 0) {
            $keys[$year . '|' . $month] = true;
        }
        $period = shaven_bill_period($bill, $installed->format('Y-m-d'));
        if (!$period || empty($period['start'])) {
            continue;
        }
        if (!empty($period['due'])) {
            $dueMonth = (int)substr($period['due'], 5, 2);
            $keys[(int)substr($period['due'], 0, 4) . '|' . strtolower(shaven_month_name($dueMonth))] = true;
        }
        $offset = shaven_anchor_offset($installed, $period['start']);
        if ($offset !== null && ($latestOffset === null || $offset > $latestOffset)) {
            $latestOffset = $offset;
        }
        if ($latestEnd === null || strcmp($period['end'], $latestEnd) > 0) {
            $latestEnd = $period['end'];
        }
    }
    if ($latestOffset !== null) {
        $begin = $latestOffset + 1;
    } elseif ($latestEnd !== null) {
        $begin = 0;
        for ($offset = 0; $offset < 600; $offset++) {
            $start = shaven_billing_anchor($installed, $offset)->format('Y-m-d');
            if (strcmp($start, $latestEnd) > 0) {
                $begin = $offset;
                break;
            }
        }
    } else {
        $today = new DateTimeImmutable('today');
        if ($installed > $today) {
            return [];
        }
        $begin = 0;
        for ($offset = 0; $offset < 600; $offset++) {
            $start = shaven_billing_anchor($installed, $offset);
            $next = shaven_billing_anchor($installed, $offset + 1);
            if ($start <= $today && $today < $next) {
                $begin = $offset;
                break;
            }
        }
    }
    $periods = [];
    $limit = $begin + $count + 48;
    for ($offset = $begin; $offset < $limit && count($periods) < $count; $offset++) {
        $period = shaven_period_at_offset($installed, $offset);
        $key = $period['year'] . '|' . strtolower($period['month']);
        if (isset($keys[$key])) {
            continue;
        }
        $periods[] = $period;
    }
    return $periods;
}

function shaven_payment_selection(array $openBills, array $billIds, $advanceCount) {
    $advanceCount = (int)$advanceCount;
    if ($advanceCount < 0 || $advanceCount > 12) {
        return ['ok' => false, 'error' => 'Advance payment can cover from 1 to 12 months.', 'ids' => [], 'advance' => 0];
    }
    $openIds = [];
    foreach ($openBills as $bill) {
        $openIds[] = (int)$bill['id'];
    }
    $wanted = [];
    foreach ($billIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $wanted[$id] = $id;
        }
    }
    $wanted = array_values($wanted);
    $prefix = array_slice($openIds, 0, count($wanted));
    if (count($wanted) !== count($prefix) || array_diff($wanted, $prefix) || array_diff($prefix, $wanted)) {
        return ['ok' => false, 'error' => 'Pay the earlier bills first. A later bill cannot be posted while an older one is still open.', 'ids' => [], 'advance' => 0];
    }
    if ($advanceCount > 0 && count($wanted) !== count($openIds)) {
        return ['ok' => false, 'error' => 'Include every open bill before posting an advance payment.', 'ids' => [], 'advance' => 0];
    }
    if (!$wanted && $advanceCount < 1) {
        return ['ok' => false, 'error' => 'Choose the oldest open bill, or enter advance months when the account has no open bill.', 'ids' => [], 'advance' => 0];
    }
    return ['ok' => true, 'error' => '', 'ids' => $prefix, 'advance' => $advanceCount];
}

function shaven_open_bills_ordered($conn, $subscriberId, $installedOn) {
    $rows = shaven_rows(
        $conn,
        "SELECT id, payment_amount, payment_year, payment_month, payment_day, period_start, period_end, due_date, status
         FROM tbl_payments
         WHERE payment_uid=? AND status=0",
        'i',
        [(int)$subscriberId]
    );
    foreach ($rows as &$row) {
        $row['s_doi'] = $installedOn;
    }
    unset($row);
    usort($rows, function ($left, $right) use ($installedOn) {
        $byDue = strcmp(shaven_due_sort_value($left, $installedOn), shaven_due_sort_value($right, $installedOn));
        if ($byDue !== 0) {
            return $byDue;
        }
        return (int)$left['id'] <=> (int)$right['id'];
    });
    return $rows;
}

function shaven_record_remittance($conn, $paymentId) {
    $paymentId = (int)$paymentId;
    $existing = shaven_rows($conn, "SELECT paymentid FROM tbl_remittances WHERE paymentid=? LIMIT 1", 'i', [$paymentId]);
    if ($existing) {
        return ['ok' => true, 'affected' => 0, 'id' => 0, 'error' => ''];
    }
    return shaven_exec($conn, "INSERT INTO tbl_remittances (paymentid, dt) VALUES (?, NOW())", 'i', [$paymentId]);
}

function shaven_money_cents($value) {
    if (is_int($value)) {
        return $value < 0 ? null : $value * 100;
    }
    if (is_float($value)) {
        if ($value < 0) {
            return null;
        }
        return (int)round($value * 100);
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0;
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return null;
    }
    return (int)round((float)$raw * 100);
}

function shaven_plan_posted_amount(array $openBills, $monthlyAmount, $cashCents) {
    $cashCents = (int)$cashCents;
    if ($cashCents < 1) {
        return ['ok' => false, 'error' => 'Enter an advance amount greater than zero.', 'steps' => [], 'future' => 0];
    }
    $left = $cashCents;
    $steps = [];
    foreach ($openBills as $bill) {
        if ($left < 1) {
            break;
        }
        $billCents = (int)round((float)($bill['payment_amount'] ?? 0) * 100);
        if ($billCents < 1) {
            continue;
        }
        $applied = min($left, $billCents);
        $steps[] = [
            'kind' => 'open',
            'id' => (int)$bill['id'],
            'paid_cents' => $applied,
            'remain_cents' => $billCents - $applied,
            'bill' => $bill,
        ];
        $left -= $applied;
    }
    $future = 0;
    if ($left > 0) {
        $monthlyCents = (int)round((float)$monthlyAmount * 100);
        if ($monthlyCents < 1) {
            return ['ok' => false, 'error' => 'This subscriber has no monthly amount, so the extra balance cannot be forwarded.', 'steps' => [], 'future' => 0];
        }
        while ($left > 0) {
            if ($future >= 24) {
                return ['ok' => false, 'error' => 'That amount covers more than 24 months ahead. Post a smaller amount.', 'steps' => [], 'future' => 0];
            }
            $applied = min($left, $monthlyCents);
            $steps[] = [
                'kind' => 'future',
                'paid_cents' => $applied,
                'remain_cents' => $monthlyCents - $applied,
            ];
            $left -= $applied;
            $future++;
        }
    }
    if (!$steps) {
        return ['ok' => false, 'error' => 'There is no open bill to apply that amount to.', 'steps' => [], 'future' => 0];
    }
    return ['ok' => true, 'error' => '', 'steps' => $steps, 'future' => $future];
}

function shaven_amount_summary($cashCents, array $steps) {
    $text = 'Posted ₱' . number_format(((int)$cashCents) / 100, 2) . ' from the oldest bill forward.';
    if (!$steps) {
        return $text;
    }
    $last = $steps[count($steps) - 1];
    if ((int)$last['remain_cents'] > 0) {
        $text .= ' ₱' . number_format(((int)$last['remain_cents']) / 100, 2) . ' remains open on the last period.';
    } elseif (count($steps) > 1) {
        $text .= ' It covered ' . count($steps) . ' periods in full.';
    }
    return $text;
}

function shaven_period_fields(array $source) {
    $clean = function ($value) {
        $value = substr((string)$value, 0, 10);
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return null;
        }
        return $value;
    };
    return [
        'year' => (int)($source['year'] ?? $source['payment_year'] ?? 0),
        'month' => (string)($source['month'] ?? $source['payment_month'] ?? ''),
        'day' => (int)($source['day'] ?? $source['payment_day'] ?? 0),
        'start' => $clean($source['start'] ?? $source['period_start'] ?? ''),
        'end' => $clean($source['end'] ?? $source['period_end'] ?? ''),
        'due' => $clean($source['due'] ?? $source['due_date'] ?? ''),
    ];
}

function shaven_insert_counter_payment($conn, $subscriberId, $userId, $stamp, array $period, $amount, $paid, $advance, $channel = 'cash') {
    $fields = shaven_period_fields($period);
    $amount = round((float)$amount, 2);
    $channel = shaven_payment_medium($channel);
    if ($channel === '') {
        $channel = 'cash';
    }
    if ($paid) {
        $receipt = shaven_receipt_number();
        $sql = "INSERT INTO tbl_payments
            (payment_year, payment_amount, payment_day, payment_month, payment_uid, p_timestamp, rf, pay_channel, period_start, period_end, due_date, status, postedby, payment_date";
        $values = "VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 1, ?, ?";
        if ($advance && shaven_column_exists($conn, 'tbl_payments', 'payment_status')) {
            $sql .= ", payment_status";
            $values .= ", '2'";
        }
        $saved = shaven_exec(
            $conn,
            $sql . ") " . $values . ")",
            'idisisssssis',
            [$fields['year'], $amount, $fields['day'], $fields['month'], (int)$subscriberId, $receipt, $channel, $fields['start'], $fields['end'], $fields['due'], (int)$userId, $stamp]
        );
        if (!$saved['ok'] || (int)$saved['id'] < 1) {
            return ['ok' => false, 'id' => 0, 'error' => 'The payment was not saved.'];
        }
        $remit = shaven_record_remittance($conn, (int)$saved['id']);
        if (!$remit['ok']) {
            return ['ok' => false, 'id' => 0, 'error' => 'The payment was not added to the remittance list.'];
        }
        return ['ok' => true, 'id' => (int)$saved['id'], 'error' => ''];
    }
    $saved = shaven_exec(
        $conn,
        "INSERT INTO tbl_payments
            (payment_year, payment_amount, payment_day, payment_month, payment_uid, p_timestamp, rf, pay_channel, period_start, period_end, due_date, status)
         VALUES (?, ?, ?, ?, ?, NOW(), '', 'bill', ?, ?, ?, 0)",
        'idisisss',
        [$fields['year'], $amount, $fields['day'], $fields['month'], (int)$subscriberId, $fields['start'], $fields['end'], $fields['due']]
    );
    if (!$saved['ok'] || (int)$saved['id'] < 1) {
        return ['ok' => false, 'id' => 0, 'error' => 'The remaining balance was not saved.'];
    }
    return ['ok' => true, 'id' => (int)$saved['id'], 'error' => ''];
}

function shaven_post_advance_amount($conn, array $profile, array $open, $cashCents, $userId, $channel = 'cash') {
    $channel = shaven_payment_medium($channel);
    if ($channel === '') {
        $channel = 'cash';
    }
    $plan = shaven_plan_posted_amount($open, $profile['p_monthly_amount'] ?? 0, $cashCents);
    if (!$plan['ok']) {
        return ['ok' => false, 'error' => $plan['error'], 'receipts' => []];
    }
    $periods = [];
    if ((int)$plan['future'] > 0) {
        $installed = shaven_install_date($profile['s_doi'] ?? '');
        if (!$installed) {
            return ['ok' => false, 'error' => 'This subscriber has no installation date, so the extra balance cannot be forwarded.', 'receipts' => []];
        }
        $history = shaven_rows(
            $conn,
            "SELECT payment_year, payment_month, payment_day, period_start, period_end, due_date
             FROM tbl_payments WHERE payment_uid=?",
            'i',
            [(int)$profile['id']]
        );
        $periods = shaven_advance_periods($installed, $history, (int)$plan['future']);
        if (count($periods) !== (int)$plan['future']) {
            return ['ok' => false, 'error' => 'The next billing period could not be calculated.', 'receipts' => []];
        }
    }
    $stamp = date('Y-m-d H:i:s');
    $receipts = [];
    $futureIndex = 0;
    mysqli_begin_transaction($conn);
    foreach ($plan['steps'] as $step) {
        $paidAmount = ((int)$step['paid_cents']) / 100;
        $remainAmount = ((int)$step['remain_cents']) / 100;
        if ($step['kind'] === 'open') {
            $bill = $step['bill'];
            if ((int)$step['remain_cents'] === 0) {
                $receipt = shaven_receipt_number();
                $posted = shaven_exec(
                    $conn,
                    "UPDATE tbl_payments
                     SET status=1, postedby=?, payment_date=?, rf=?, pay_channel=?
                     WHERE id=? AND payment_uid=? AND status=0",
                    'isssii',
                    [(int)$userId, $stamp, $receipt, $channel, (int)$step['id'], (int)$profile['id']]
                );
                if (!$posted['ok'] || (int)$posted['affected'] !== 1) {
                    mysqli_rollback($conn);
                    return ['ok' => false, 'error' => 'One of those bills is already paid or no longer open.', 'receipts' => []];
                }
                $remit = shaven_record_remittance($conn, (int)$step['id']);
                if (!$remit['ok']) {
                    mysqli_rollback($conn);
                    return ['ok' => false, 'error' => 'The payment was not added to the remittance list.', 'receipts' => []];
                }
                $receipts[] = (int)$step['id'];
                continue;
            }
            $reduced = shaven_exec(
                $conn,
                "UPDATE tbl_payments
                 SET payment_amount=?
                 WHERE id=? AND payment_uid=? AND status=0 AND ROUND(payment_amount, 2)=?",
                'diid',
                [$remainAmount, (int)$step['id'], (int)$profile['id'], round((float)$bill['payment_amount'], 2)]
            );
            if (!$reduced['ok'] || (int)$reduced['affected'] !== 1) {
                mysqli_rollback($conn);
                return ['ok' => false, 'error' => 'One of those bills is already paid or no longer open.', 'receipts' => []];
            }
            $saved = shaven_insert_counter_payment($conn, (int)$profile['id'], $userId, $stamp, $bill, $paidAmount, true, false, $channel);
            if (!$saved['ok']) {
                mysqli_rollback($conn);
                return ['ok' => false, 'error' => $saved['error'], 'receipts' => []];
            }
            $receipts[] = (int)$saved['id'];
            continue;
        }
        $period = $periods[$futureIndex] ?? null;
        $futureIndex++;
        if (!$period) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'The next billing period could not be calculated.', 'receipts' => []];
        }
        $saved = shaven_insert_counter_payment($conn, (int)$profile['id'], $userId, $stamp, $period, $paidAmount, true, true, $channel);
        if (!$saved['ok']) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => $saved['error'], 'receipts' => []];
        }
        $receipts[] = (int)$saved['id'];
        if ((int)$step['remain_cents'] > 0) {
            $openBill = shaven_insert_counter_payment($conn, (int)$profile['id'], $userId, $stamp, $period, $remainAmount, false, false);
            if (!$openBill['ok']) {
                mysqli_rollback($conn);
                return ['ok' => false, 'error' => $openBill['error'], 'receipts' => []];
            }
        }
    }
    mysqli_commit($conn);
    return [
        'ok' => true,
        'error' => '',
        'receipts' => $receipts,
        'summary' => shaven_amount_summary($cashCents, $plan['steps']),
    ];
}

function shaven_counter_collect($conn, $subscriberId, array $billIds, $advanceCount, $userId, $advanceAmount = 0, $medium = 'cash') {
    if (!shaven_role_is(1, 2, 3)) {
        return ['ok' => false, 'error' => 'Payments are posted by the office or a technician.', 'receipts' => []];
    }
    $medium = shaven_payment_medium($medium);
    if ($medium === '') {
        return ['ok' => false, 'error' => 'Choose Cash or GCash.', 'receipts' => []];
    }
    $subscriberId = (int)$subscriberId;
    $userId = (int)$userId;
    $profileRows = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_doi, s.status, pl.p_monthly_amount
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
         WHERE s.id=? LIMIT 1",
        'i',
        [$subscriberId]
    );
    if (!$profileRows || (int)$profileRows[0]['status'] !== 1) {
        return ['ok' => false, 'error' => 'That subscriber is not an active account.', 'receipts' => []];
    }
    $profile = $profileRows[0];
    $open = shaven_open_bills_ordered($conn, $subscriberId, $profile['s_doi']);
    $cashCents = shaven_money_cents($advanceAmount);
    if ($cashCents === null) {
        return ['ok' => false, 'error' => 'Enter the advance amount in pesos, with up to two decimal places.', 'receipts' => []];
    }
    if ($cashCents > 0) {
        if ((int)$advanceCount > 0) {
            return ['ok' => false, 'error' => 'Enter an advance amount or a number of advance months, not both.', 'receipts' => []];
        }
        $posted = shaven_post_advance_amount($conn, $profile, $open, $cashCents, $userId, $medium);
        if ($posted['ok']) {
            shaven_notify_payment_posted($conn, $subscriberId, $userId, $posted['receipts'], false);
        }
        return $posted;
    }
    $selection = shaven_payment_selection($open, $billIds, $advanceCount);
    if (!$selection['ok']) {
        return ['ok' => false, 'error' => $selection['error'], 'receipts' => []];
    }
    $installed = shaven_install_date($profile['s_doi']);
    $amount = round((float)$profile['p_monthly_amount'], 2);
    $advances = [];
    if ((int)$selection['advance'] > 0) {
        if (!$installed) {
            return ['ok' => false, 'error' => 'This subscriber has no installation date, so an advance period cannot be calculated.', 'receipts' => []];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'This subscriber has no monthly amount, so an advance payment cannot be posted.', 'receipts' => []];
        }
        $history = shaven_rows(
            $conn,
            "SELECT payment_year, payment_month, payment_day, period_start, period_end, due_date
             FROM tbl_payments WHERE payment_uid=?",
            'i',
            [$subscriberId]
        );
        $advances = shaven_advance_periods($installed, $history, (int)$selection['advance']);
        if (count($advances) !== (int)$selection['advance']) {
            return ['ok' => false, 'error' => 'The next advance periods could not be calculated.', 'receipts' => []];
        }
    }
    $stamp = date('Y-m-d H:i:s');
    $hasAdvanceFlag = shaven_column_exists($conn, 'tbl_payments', 'payment_status');
    $receipts = [];
    mysqli_begin_transaction($conn);
    foreach ($selection['ids'] as $id) {
        $receipt = shaven_receipt_number();
        $posted = shaven_exec(
            $conn,
            "UPDATE tbl_payments
             SET status=1, postedby=?, payment_date=?, rf=?, pay_channel=?
             WHERE id=? AND payment_uid=? AND status=0",
            'isssii',
            [$userId, $stamp, $receipt, $medium, $id, $subscriberId]
        );
        if (!$posted['ok'] || (int)$posted['affected'] !== 1) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'One of those bills is already paid or no longer open.', 'receipts' => []];
        }
        $remit = shaven_record_remittance($conn, $id);
        if (!$remit['ok']) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'The payment was not added to the remittance list.', 'receipts' => []];
        }
        $receipts[] = $id;
    }
    foreach ($advances as $period) {
        $receipt = shaven_receipt_number();
        $sql = "INSERT INTO tbl_payments
            (payment_year, payment_amount, payment_day, payment_month, payment_uid, p_timestamp, rf, pay_channel, period_start, period_end, due_date, status, postedby, payment_date";
        $values = "VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 1, ?, ?";
        if ($hasAdvanceFlag) {
            $sql .= ", payment_status";
            $values .= ", '2'";
        }
        $saved = shaven_exec(
            $conn,
            $sql . ") " . $values . ")",
            'idisisssssis',
            [$period['year'], $amount, $period['day'], $period['month'], $subscriberId, $receipt, $medium, $period['start'], $period['end'], $period['due'], $userId, $stamp]
        );
        if (!$saved['ok'] || (int)$saved['id'] < 1) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'The advance payment was not saved.', 'receipts' => []];
        }
        $remit = shaven_record_remittance($conn, (int)$saved['id']);
        if (!$remit['ok']) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => 'The advance payment was not added to the remittance list.', 'receipts' => []];
        }
        $receipts[] = (int)$saved['id'];
    }
    mysqli_commit($conn);
    shaven_notify_payment_posted($conn, $subscriberId, $userId, $receipts, false);
    return ['ok' => true, 'error' => '', 'receipts' => $receipts];
}

function shaven_payment_desk($conn, $subscriberId) {
    $subscribers = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_address, s.s_doi, pl.p_name, pl.p_speed_kb, pl.p_monthly_amount,
                COALESCE(o.open_count, 0) AS open_count
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
         LEFT JOIN (
            SELECT payment_uid, COUNT(*) AS open_count
            FROM tbl_payments
            WHERE status=0
            GROUP BY payment_uid
         ) o ON o.payment_uid = s.id
         WHERE s.status=1
         ORDER BY s.s_name"
    );
    $subscriberId = (int)$subscriberId;
    $profile = null;
    foreach ($subscribers as $row) {
        if ((int)$row['id'] === $subscriberId) {
            $profile = $row;
            break;
        }
    }
    $open = [];
    $advances = [];
    if ($profile) {
        $open = shaven_open_bills_ordered($conn, $subscriberId, $profile['s_doi']);
        $installed = shaven_install_date($profile['s_doi']);
        if ($installed && (float)$profile['p_monthly_amount'] > 0) {
            $history = shaven_rows(
                $conn,
                "SELECT payment_year, payment_month, payment_day, period_start, period_end, due_date
                 FROM tbl_payments WHERE payment_uid=?",
                'i',
                [$subscriberId]
            );
            $advances = shaven_advance_periods($installed, $history, 24);
        }
    }
    return [
        'subscribers' => $subscribers,
        'profile' => $profile,
        'requested_id' => $subscriberId,
        'open' => $open,
        'advances' => $advances,
    ];
}

function shaven_username_base($name) {
    $base = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', (string)$name));
    $base = substr($base, 0, 16);
    return $base !== '' ? $base : 'subscriber';
}

function shaven_unique_username($conn, $base) {
    $base = shaven_username_base($base);
    $candidate = $base;
    for ($n = 0; $n < 60; $n++) {
        $rows = shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE username=? LIMIT 1", 's', [$candidate]);
        if (!$rows) {
            return $candidate;
        }
        $candidate = $base . ($n + 1);
    }
    return $base . random_int(1000, 9999);
}

function shaven_temp_password() {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function shaven_active_owner_count($conn, $exceptId = 0) {
    $rows = shaven_rows(
        $conn,
        "SELECT COUNT(*) c FROM tbl_sys_users WHERE sys_level=1 AND status=1 AND id<>?",
        'i',
        [(int)$exceptId]
    );
    return (int)($rows[0]['c'] ?? 0);
}

function shaven_create_login($conn, $username, $password, $fullname, $level, $status, $subscriberId) {
    $username = trim((string)$username);
    $password = (string)$password;
    $fullname = trim((string)$fullname);
    $level = (int)$level;
    $status = (int)$status === 1 ? 1 : 0;
    $subscriberId = (int)$subscriberId;
    if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
        return ['ok' => false, 'error' => 'Usernames use 3 to 32 letters, numbers, dots, or dashes.'];
    }
    if (strlen($password) < 4) {
        return ['ok' => false, 'error' => 'Use a password of at least 4 characters.'];
    }
    if ($fullname === '') {
        return ['ok' => false, 'error' => 'Enter the person\'s name.'];
    }
    if (!in_array($level, [1, 2, 3, 4], true)) {
        return ['ok' => false, 'error' => 'Choose Owner, Admin, Technician, or Subscriber.'];
    }
    if ($level === 4 && $subscriberId <= 0) {
        return ['ok' => false, 'error' => 'Link a subscriber record to this login.'];
    }
    if ($level !== 4) {
        $subscriberId = 0;
    }
    if (shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE username=? LIMIT 1", 's', [$username])) {
        return ['ok' => false, 'error' => 'That username is already in use.'];
    }
    if ($subscriberId > 0) {
        $sub = shaven_rows($conn, "SELECT id, s_name FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
        if (!$sub) {
            return ['ok' => false, 'error' => 'Choose a subscriber from the list.'];
        }
        $taken = shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE subscriber_id=? AND sys_level=4 LIMIT 1", 'i', [$subscriberId]);
        if ($taken) {
            return ['ok' => false, 'error' => 'That subscriber already has a portal login.'];
        }
        if ($fullname === '') {
            $fullname = $sub[0]['s_name'];
        }
    }
    if ($subscriberId > 0) {
        $result = shaven_exec(
            $conn,
            "INSERT INTO tbl_sys_users (username, password, sys_user_fullname, sys_user_dor, sys_level, status, subscriber_id)
             VALUES (?, ?, ?, NOW(), ?, ?, ?)",
            'sssiii',
            [$username, $password, $fullname, $level, $status, $subscriberId]
        );
    } else {
        $result = shaven_exec(
            $conn,
            "INSERT INTO tbl_sys_users (username, password, sys_user_fullname, sys_user_dor, sys_level, status)
             VALUES (?, ?, ?, NOW(), ?, ?)",
            'sssii',
            [$username, $password, $fullname, $level, $status]
        );
    }
    if (!$result['ok']) {
        return ['ok' => false, 'error' => 'The account was not created.'];
    }
    $newId = (int)$result['id'];
    if ($status === 1 && $newId > 0) {
        $actorId = (int)($_SESSION['usr_id'] ?? 0);
        shaven_notify(
            $conn,
            $newId,
            'account',
            'Your account is ready',
            'Sign in as ' . $username . '. Your role is ' . shaven_role_name($level) . '.',
            'index.php',
            $actorId
        );
        $office = array_values(array_diff(shaven_notify_user_ids($conn, [1, 2], $actorId), [$newId]));
        shaven_notify(
            $conn,
            $office,
            'account',
            'Account created',
            $fullname . ' can sign in as ' . $username . ' (' . shaven_role_name($level) . ').',
            'pages/admin/users.php',
            $actorId
        );
    }
    return ['ok' => true, 'error' => '', 'id' => $newId];
}

function shaven_provision_subscriber($conn, $subscriberId) {
    $subscriberId = (int)$subscriberId;
    $rows = shaven_rows($conn, "SELECT id, s_name FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
    if (!$rows) {
        return ['ok' => false, 'error' => 'That subscriber record was not found.'];
    }
    $password = shaven_temp_password();
    $username = shaven_unique_username($conn, $rows[0]['s_name']);
    $created = shaven_create_login($conn, $username, $password, $rows[0]['s_name'], 4, 1, $subscriberId);
    if (!$created['ok']) {
        return $created;
    }
    return ['ok' => true, 'error' => '', 'username' => $username, 'password' => $password];
}

function shaven_reset_portal_password($conn, $subscriberId) {
    $subscriberId = (int)$subscriberId;
    $rows = shaven_rows(
        $conn,
        "SELECT id, username FROM tbl_sys_users WHERE subscriber_id=? AND sys_level=4 LIMIT 1",
        'i',
        [$subscriberId]
    );
    if (!$rows) {
        return ['ok' => false, 'error' => 'This subscriber does not have a portal login yet.'];
    }
    $password = shaven_temp_password();
    $result = shaven_exec($conn, "UPDATE tbl_sys_users SET password=?, status=1 WHERE id=?", 'si', [$password, (int)$rows[0]['id']]);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => 'The password was not reset.'];
    }
    shaven_notify(
        $conn,
        (int)$rows[0]['id'],
        'account',
        'Password reset',
        'The office reset the password for ' . $rows[0]['username'] . '. Sign in with the new password they gave you.',
        'pages/account/profile.php',
        (int)($_SESSION['usr_id'] ?? 0)
    );
    return ['ok' => true, 'username' => $rows[0]['username'], 'password' => $password, 'error' => ''];
}

require_once __DIR__ . '/remittance.php';
require_once __DIR__ . '/revert.php';
require_once __DIR__ . '/owner_batch.php';
require_once __DIR__ . '/bill_adjust.php';
require_once __DIR__ . '/collection_records.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/db_tools.php';
require_once __DIR__ . '/collector_ledger.php';
require_once __DIR__ . '/theme.php';
