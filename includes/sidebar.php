<?php
if (!function_exists('shaven_role')) {
    require_once __DIR__ . '/bootstrap.php';
    if (PHP_SAPI !== 'cli') {
        shaven_web_boot(false);
    }
}

function shaven_nav_item($path, $icon, $label) {
    $current = shaven_rel_script();
    $active = ($current === '/' . ltrim($path, '/')) ? ' active' : '';
    echo '<li class="nav-item"><a href="' . h(shaven_url($path)) . '" class="nav-link' . $active . '">';
    echo '<i class="' . h($icon) . ' nav-icon"></i><p>' . h($label) . '</p></a></li>';
}

function shaven_nav_group($icon, $label, $items) {
    $current = shaven_rel_script();
    $open = false;
    foreach ($items as $item) {
        if ($current === '/' . ltrim($item[0], '/')) {
            $open = true;
            break;
        }
    }
    echo '<li class="nav-item' . ($open ? ' menu-open' : '') . '"><a href="#" class="nav-link"><i class="nav-icon ' . h($icon) . '"></i><p>' . h($label) . '<i class="right fas fa-angle-left"></i></p></a>';
    echo '<ul class="nav nav-treeview">';
    foreach ($items as $item) {
        shaven_nav_item($item[0], $item[2], $item[1]);
    }
    echo '</ul></li>';
}

$role = shaven_role();
$profileUrl = shaven_url('pages/account/profile.php');
$photoUrl = shaven_account_photo_url($_SESSION['photo'] ?? '');
?>
<div class="sidebar">
  <div class="user-panel mt-3 pb-3 mb-3 d-flex">
    <div class="image">
      <a href="<?php echo h($profileUrl); ?>"><img src="<?php echo h($photoUrl); ?>" class="img-circle elevation-2 shaven-avatar" alt=""></a>
    </div>
    <div class="info">
      <a href="<?php echo h($profileUrl); ?>" class="d-block"><?php echo h($_SESSION['fullname'] ?? 'Account'); ?></a>
      <span class="d-block text-xs" style="color:#c2c7d0;"><?php echo h(shaven_role_name($role)); ?></span>
    </div>
  </div>
  <nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
      <?php if ($role === 4): ?>
        <?php shaven_nav_item('index.php', 'fas fa-home', 'Home'); ?>
        <?php shaven_nav_item('pages/account/index.php', 'fas fa-id-card', 'My Account'); ?>
        <?php shaven_nav_item('pages/account/pay.php', 'fas fa-money-bill-wave', 'Pay Bill'); ?>
        <?php shaven_nav_item('pages/account/plan.php', 'fas fa-exchange-alt', 'Change Plan'); ?>
      <?php elseif ($role === 3): ?>
        <?php shaven_nav_item('index.php', 'fas fa-tachometer-alt', 'Dashboard'); ?>
        <?php
          shaven_nav_group('fas fa-tools', 'Service', [
            ['pages/tables/reports.php', 'Subscribers', 'fas fa-address-book'],
          ]);
          shaven_nav_group('fas fa-hand-holding-usd', 'Collections', [
            ['pages/tables/tinglunon.php', 'Tinglunon', 'fas fa-file-image'],
            ['pages/tables/collections.php', 'Post Payment', 'fas fa-cash-register'],
            ['pages/tables/collections_records.php', 'Collection Records', 'fas fa-table'],
            ['pages/tables/remittances.php', 'Remittances', 'fas fa-wallet'],
            ['pages/tables/collections_revert.php', 'Revert Payment', 'fas fa-undo'],
          ]);
        ?>
      <?php else: ?>
        <?php shaven_nav_item('index.php', 'fas fa-tachometer-alt', 'Dashboard'); ?>
        <?php
          $subscriberItems = [
            ['pages/forms/sub.addnew.php', 'Add Subscriber', 'fas fa-user-plus'],
            ['pages/tables/reports.php', 'Subscribers', 'fas fa-address-book'],
            ['pages/admin/plans.php', 'Plans', 'fas fa-tags'],
            ['pages/admin/billing.php', 'Billing', 'fas fa-file-invoice-dollar'],
            ['pages/tables/collections.php', 'Post Payment', 'fas fa-cash-register'],
            ['pages/tables/collections_revert.php', 'Revert Payment', 'fas fa-undo'],
          ];
          if ($role === 1 || $role === 2) {
            $subscriberItems[] = ['pages/tables/collections_revert_approval.php', 'Approve Revert', 'fas fa-check-circle'];
          }
          if ($role === 1 || $role === 2) {
            $subscriberItems[] = ['pages/tables/remittances.php', 'Remittances', 'fas fa-wallet'];
          }
          if ($role === 1) {
            $subscriberItems[] = ['pages/tables/unremitted_bills.php', 'Unremitted Bills', 'fas fa-check-double'];
          }
          shaven_nav_group('fas fa-users', 'Subscribers', $subscriberItems);
          shaven_nav_group('fas fa-list-alt', 'Collections', [
            ['pages/tables/tinglunon.php', 'Tinglunon', 'fas fa-file-image'],
            ['pages/tables/collections_posted.php', 'My Collections', 'fas fa-hand-holding-usd'],
            ['pages/tables/collections_records.php', 'Collection Records', 'fas fa-table'],
          ]);
          shaven_nav_item('pages/admin/users.php', 'fas fa-users-cog', 'Accounts');
        ?>
      <?php endif; ?>
      <?php shaven_nav_item('pages/account/profile.php', 'fas fa-user-circle', 'Account profile'); ?>
      <?php shaven_nav_item('logout.php', 'fas fa-sign-out-alt', 'Sign Out'); ?>
    </ul>
  </nav>
</div>
