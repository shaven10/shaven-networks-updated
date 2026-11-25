<?php
if (!function_exists('shaven_url')) {
    require_once __DIR__ . '/bootstrap.php';
}
$shavenHome = shaven_url('index.php');
$shavenProfile = shaven_url('pages/account/profile.php');
$shavenLogout = shaven_url('logout.php');
$shavenNotify = shaven_url('pages/account/notifications.php');
$shavenPhoto = shaven_account_photo_url($_SESSION['photo'] ?? '');
$shavenName = $_SESSION['fullname'] ?? 'Account';
$shavenNotifyMenu = ['unread' => 0, 'html' => '<span class="dropdown-item dropdown-header">Notifications</span>'];
$shavenNotifyConn = $GLOBALS['conn'] ?? null;
if ($shavenNotifyConn instanceof mysqli && function_exists('shaven_notify_menu')) {
    $shavenNotifyMenu = shaven_notify_menu($shavenNotifyConn, (int)($_SESSION['usr_id'] ?? 0));
}
$shavenNotifyUnread = (int)$shavenNotifyMenu['unread'];
$shavenNotifyLabel = $shavenNotifyUnread > 0 ? 'Notifications, ' . $shavenNotifyUnread . ' unread' : 'Notifications';
shaven_theme_assets();
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
    <li class="nav-item dropdown" id="shaven-notify" data-feed="<?php echo h($shavenNotify . '?feed=1'); ?>">
      <a id="shaven-notify-toggle" class="nav-link" data-toggle="dropdown" href="<?php echo h($shavenNotify); ?>" role="button" aria-label="<?php echo h($shavenNotifyLabel); ?>" aria-haspopup="true" aria-expanded="false">
        <i class="far fa-bell"></i>
        <span id="shaven-notify-count" class="badge navbar-badge<?php echo $shavenNotifyUnread > 0 ? '' : ' d-none'; ?>"><?php echo $shavenNotifyUnread > 99 ? '99+' : (int)$shavenNotifyUnread; ?></span>
      </a>
      <div id="shaven-notify-menu" class="dropdown-menu dropdown-menu-right shaven-notify-menu">
        <?php echo $shavenNotifyMenu['html']; ?>
      </div>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link shaven-account-toggle" data-toggle="dropdown" href="#" role="button">
        <img src="<?php echo h($shavenPhoto); ?>" class="shaven-avatar" alt="">
        <span class="d-none d-md-inline"><?php echo h($shavenName); ?></span>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <span class="dropdown-item-text text-muted"><?php echo h(shaven_role_name()); ?></span>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?php echo h($shavenNotify); ?>"><i class="fas fa-bell mr-2"></i> Notifications<?php echo $shavenNotifyUnread > 0 ? ' (' . (int)$shavenNotifyUnread . ')' : ''; ?></a>
        <a class="dropdown-item" href="<?php echo h($shavenProfile); ?>"><i class="fas fa-user-circle mr-2"></i> Account profile</a>
        <a class="dropdown-item" href="<?php echo h($shavenLogout); ?>"><i class="fas fa-sign-out-alt mr-2"></i> Sign out</a>
      </div>
    </li>
  </ul>
</nav>
