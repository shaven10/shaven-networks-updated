<?php
require_once __DIR__ . '/../../includes/guard.php';
$uid = (int)($_GET['uid'] ?? 0);
shaven_redirect('/pages/tables/collections.php' . ($uid > 0 ? '?uid=' . $uid : '') . '#pay-desk');
