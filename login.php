<?php
require_once __DIR__ . '/includes/bootstrap.php';
shaven_web_boot(false);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['user'] ?? ''));
    $password = (string)($_POST['pass'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $rows = shaven_rows(
            $conn,
            "SELECT id, sys_user_fullname, sys_level, subscriber_id FROM tbl_sys_users WHERE username=? AND password=? AND status=1 LIMIT 1",
            'ss',
            [$username, $password]
        );
        if (!$rows) {
            $error = 'Check the username and password, and make sure the account is active.';
        } else {
            session_regenerate_id(true);
            $_SESSION['usr_id'] = (int)$rows[0]['id'];
            $_SESSION['fullname'] = $rows[0]['sys_user_fullname'];
            $_SESSION['sys_level'] = (int)$rows[0]['sys_level'];
            $_SESSION['subscriber_id'] = (int)($rows[0]['subscriber_id'] ?? 0);
            shaven_redirect('/index.php');
        }
    }
} elseif (!empty($_SESSION['usr_id'])) {
    shaven_redirect('/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SHAVEN Networks | Log in</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="stylesheet" href="dist/css/shaven-theme.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="login.php"><b>SHAVEN</b> Networks</a>
  </div>
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Sign in as owner, admin, technician, or subscriber</p>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
      <?php endif; ?>
      <form action="" method="post" autocomplete="on">
        <div class="input-group mb-3">
          <input type="text" name="user" class="form-control" placeholder="Username" autocomplete="username" required>
          <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="pass" class="form-control" placeholder="Password" autocomplete="current-password" required>
          <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
        </div>
        <button type="submit" name="login" class="btn btn-primary btn-block">Sign In</button>
      </form>
    </div>
  </div>
</div>
<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
</body>
</html>
