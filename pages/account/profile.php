<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/account_profile.php';
shaven_web_boot(true);

if (!shaven_role_is(1, 2, 3, 4)) {
    shaven_redirect('/index.php?denied=1');
}
$userId = (int)($_SESSION['usr_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The profile form expired. Submit it again.', 'err');
        shaven_redirect('/pages/account/profile.php');
    }
    $saved = shaven_save_account_profile($conn, $userId, $_POST, $_FILES['photo'] ?? []);
    shaven_flash_set($saved['ok'] ? 'Account profile saved.' : $saved['error'], $saved['ok'] ? 'ok' : 'err');
    shaven_redirect('/pages/account/profile.php');
}

$rows = shaven_rows(
    $conn,
    "SELECT id, username, sys_user_fullname, photo, subscriber_id, sys_level FROM tbl_sys_users WHERE id=? LIMIT 1",
    'i',
    [$userId]
);
if (!$rows) {
    shaven_redirect('/logout.php');
}
$user = $rows[0];
$service = null;
if ((int)$user['sys_level'] === 4 && (int)$user['subscriber_id'] > 0) {
    $serviceRows = shaven_rows($conn, "SELECT s_name, s_address FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [(int)$user['subscriber_id']]);
    $service = $serviceRows[0] ?? null;
}
$photoUrl = shaven_account_photo_url($user['photo'] ?? '');
shaven_page_open('Account profile', 'Account profile');
?>
<div class="row">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body text-center">
        <img src="<?php echo h($photoUrl); ?>" id="photo-preview" class="shaven-profile-photo" alt="">
        <h2 class="h4 mt-3 mb-1"><?php echo h($user['sys_user_fullname']); ?></h2>
        <p class="text-muted mb-0"><?php echo h(shaven_role_name($user['sys_level'])); ?> · <?php echo h($user['username']); ?></p>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <form method="post" enctype="multipart/form-data" class="card">
      <div class="card-header"><h3 class="card-title">Account details</h3></div>
      <div class="card-body">
        <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
        <div class="form-group">
          <label for="fullname">Name</label>
          <input class="form-control" id="fullname" name="fullname" maxlength="255" required value="<?php echo h($user['sys_user_fullname']); ?>">
        </div>
        <div class="form-group">
          <label for="username">Username</label>
          <input class="form-control" id="username" name="username" maxlength="32" required value="<?php echo h($user['username']); ?>">
        </div>
        <?php if ($service): ?>
          <div class="form-group">
            <label for="s_address">Service address</label>
            <textarea class="form-control" id="s_address" name="s_address" rows="3" maxlength="255"><?php echo h($service['s_address']); ?></textarea>
          </div>
        <?php endif; ?>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label for="current_password">Current password</label>
            <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password">
          </div>
          <div class="form-group col-md-4">
            <label for="password">New password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password">
          </div>
          <div class="form-group col-md-4">
            <label for="password_confirm">Confirm new password</label>
            <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password">
          </div>
        </div>
        <p class="text-muted">Leave the password fields blank to keep the current password. A new password needs the current one.</p>
        <div class="form-group">
          <label for="photo">Account picture</label>
          <input class="form-control-file" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
          <small class="form-text text-muted">JPG, PNG, or GIF, up to 2 MB.</small>
        </div>
        <?php if (trim((string)($user['photo'] ?? '')) !== ''): ?>
          <div class="form-check">
            <input class="form-check-input" id="remove_photo" name="remove_photo" type="checkbox" value="1">
            <label class="form-check-label" for="remove_photo">Remove the current picture</label>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <button class="btn btn-primary" type="submit">Save profile</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('photo');
  var preview = document.getElementById('photo-preview');
  if (!input || !preview) return;
  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
  });
});
</script>
<?php shaven_page_close(); ?>
