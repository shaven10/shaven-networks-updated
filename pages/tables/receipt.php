<?php
require_once __DIR__ . '/../../includes/guard.php';
require_once __DIR__ . '/../../includes/receipt_doc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usr_id'])) {
    header('Location: ../../login.php');
    exit;
}

$bundle = shaven_receipt_bundle($conn, shaven_receipt_request_ids());
if (!$bundle['ok']) {
    shaven_receipt_error($bundle);
    exit;
}
if (isset($_GET['download'])) {
    shaven_receipt_send_image($bundle);
    exit;
}

$flash = null;
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
$back = shaven_role_is(4)
    ? shaven_url('pages/account/index.php')
    : shaven_url('pages/tables/collections.php?uid=' . (int)$bundle['uid'] . '#pay-desk');
shaven_receipt_page($bundle, $flash, $back);
