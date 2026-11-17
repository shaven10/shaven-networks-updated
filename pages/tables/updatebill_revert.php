<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
shaven_web_boot(true);
shaven_flash_set('Ask for a revert from Revert Payment. An admin or the owner approves it when the payment has not been remitted.', 'err');
shaven_redirect('/pages/tables/collections_revert.php');
