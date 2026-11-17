<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
shaven_web_boot(true);
shaven_flash_set('Approve a revert from Approve Revert. The payment has to be unremitted, and another admin or the owner approves a request you made.', 'err');
shaven_redirect(shaven_role_is(1, 2) ? '/pages/tables/collections_revert_approval.php' : '/pages/tables/collections_revert.php');
