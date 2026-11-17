<?php
require_once __DIR__ . '/../../includes/guard.php';
$id = (int)($_GET['id'] ?? 0);
$target = 'receipt.php?id=' . $id;
if (!empty($_GET['download'])) {
    $target .= '&download=1';
}
header('Location: ' . $target);
exit;
