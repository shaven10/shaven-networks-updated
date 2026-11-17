<?php
if (!function_exists('shaven_url')) {
    require_once __DIR__ . '/bootstrap.php';
}
$shavenHome = shaven_url('index.php');
$shavenProfile = shaven_url('pages/account/profile.php');
$shavenLogout = shaven_url('logout.php');
$shavenPhoto = shaven_account_photo_url($_SESSION['photo'] ?? '');
$shavenName = $_SESSION['fullname'] ?? 'Account';
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?php echo h($shavenHome); ?>" class="nav-link"><i class="fas fa-home mr-1"></i> Home</a>
    </li>
  </ul>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item dropdown">
      <a class="nav-link shaven-account-toggle" data-toggle="dropdown" href="#" role="button">
        <img src="<?php echo h($shavenPhoto); ?>" class="shaven-avatar" alt="">
        <span class="d-none d-md-inline"><?php echo h($shavenName); ?></span>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <span class="dropdown-item-text text-muted"><?php echo h(shaven_role_name()); ?></span>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?php echo h($shavenProfile); ?>"><i class="fas fa-user-circle mr-2"></i> Account profile</a>
        <a class="dropdown-item" href="<?php echo h($shavenLogout); ?>"><i class="fas fa-sign-out-alt mr-2"></i> Sign out</a>
      </div>
    </li>
  </ul>
</nav>
