<?php
require_once __DIR__ . '/../../includes/guard.php';
$uid = (int)($_POST['sid'] ?? 0);
shaven_flash_set('Advance payments are posted from Post Payment, after the open bills.', 'err');
shaven_redirect('/pages/tables/collections.php' . ($uid > 0 ? '?uid=' . $uid : '') . '#pay-desk');
