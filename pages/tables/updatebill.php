<?php
require_once __DIR__ . '/../../includes/guard.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usr_id'])) {
    header('location:login.php');
    exit;
}

$pid = (int)($_GET['payment_id'] ?? 0);
$rows = shaven_rows($conn, "SELECT id, payment_uid, status FROM tbl_payments WHERE id=? LIMIT 1", 'i', [$pid]);
if (!$rows) {
    echo '<script>alert("That bill was not found."); window.location = "collections.php";</script>';
    exit;
}
$uid = (int)$rows[0]['payment_uid'];
$desk = 'collections.php?uid=' . $uid . '#pay-desk';
if ((string)$rows[0]['status'] !== '0' && (int)$rows[0]['status'] !== 0) {
    echo '<script>alert("That bill is already posted."); window.location = "' . $desk . '";</script>';
    exit;
}
$result = shaven_counter_collect($conn, $uid, [$pid], 0, (int)$_SESSION['usr_id']);
if (!$result['ok']) {
    echo '<script>alert(' . json_encode($result['error']) . '); window.location = "' . $desk . '";</script>';
    exit;
}
$posted = (int)$result['receipts'][0];
header('Location: receipt.php?id=' . $posted);
exit;
