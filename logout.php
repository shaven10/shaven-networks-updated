<?php
require_once __DIR__ . '/includes/bootstrap.php';
shaven_web_boot(false);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
shaven_redirect('/login.php');
