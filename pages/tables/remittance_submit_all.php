<?php
require_once __DIR__ . '/../../includes/guard.php';
shaven_flash_set('Remittances are submitted as a batch. An admin confirms the batch before the cash is recorded as received.', 'err');
shaven_redirect('/pages/tables/remittances.php');
