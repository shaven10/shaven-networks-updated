<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
shaven_web_boot(true);
shaven_flash_set('Cancel a revert request from Revert Payment.', 'err');
shaven_redirect('/pages/tables/collections_revert.php');
