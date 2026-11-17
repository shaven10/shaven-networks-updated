<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
shaven_web_boot(true);
if (!shaven_role_is(1, 2)) {
    shaven_redirect('/index.php?denied=1');
}
if (!shaven_csrf_ok()) {
    echo '<script>alert("The plan form expired. Open it again."); history.back();</script>';
    exit;
}
$planId = (int)($_POST['plan_id'] ?? 0);
$sid = (int)($_POST['sid'] ?? 0);
$result = shaven_change_plan($conn, $sid, $planId, (int)$_SESSION['usr_id'], 'Updated from the office plan form');
if ($result === true) {
    echo '<script>alert("Plan update successful."); window.location = "../tables/subscriber_view.php?uid=' . $sid . '";</script>';
    exit;
}
echo '<script>alert(' . json_encode($result) . '); history.back();</script>';
