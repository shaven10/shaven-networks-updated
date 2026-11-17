<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/dashboard.php';
shaven_web_boot(true);

$role = shaven_role();
$heading = $role === 4 ? 'My Service' : 'Dashboard';
shaven_page_open($heading, $heading);
if ($role === 4) {
    shaven_render_subscriber_dashboard($conn);
} elseif ($role === 3) {
    shaven_render_tech_dashboard($conn);
} else {
    shaven_render_staff_dashboard($conn);
}
shaven_page_close();
