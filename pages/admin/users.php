<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);
if (!shaven_role_is(1, 2)) {
    shaven_redirect('/index.php?denied=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The account form expired. Submit it again.', 'err');
        shaven_redirect('/pages/admin/users.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $created = shaven_create_login(
            $conn,
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['fullname'] ?? '',
            (int)($_POST['sys_level'] ?? 0),
            1,
            (int)($_POST['subscriber_id'] ?? 0)
        );
        shaven_flash_set($created['ok'] ? 'The account is ready to sign in.' : $created['error'], $created['ok'] ? 'ok' : 'err');
        shaven_redirect('/pages/admin/users.php');
    }
    if ($action === 'save') {
        $id = (int)($_POST['user_id'] ?? 0);
        $fullname = trim((string)($_POST['fullname'] ?? ''));
        $level = (int)($_POST['sys_level'] ?? 0);
        $status = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');
        $subscriberId = (int)($_POST['subscriber_id'] ?? 0);
        $actor = (int)$_SESSION['usr_id'];
        $rows = shaven_rows($conn, "SELECT id, sys_level, status FROM tbl_sys_users WHERE id=? LIMIT 1", 'i', [$id]);
        $error = '';
        if (!$rows) {
            $error = 'That account was not found.';
        } elseif ($fullname === '' || !in_array($level, [1, 2, 3, 4], true)) {
            $error = 'Enter a name and a role.';
        } elseif ($password !== '' && strlen($password) < 4) {
            $error = 'Use a password of at least 4 characters.';
        } elseif ($id === $actor && $status !== 1) {
            $error = 'Keep your own account active while you are signed in.';
        } elseif ((int)$rows[0]['sys_level'] === 1 && (int)$rows[0]['status'] === 1 && ($level !== 1 || $status !== 1) && shaven_active_owner_count($conn, $id) < 1) {
            $error = 'Keep at least one active owner account.';
        } elseif ($level === 4) {
            $sub = shaven_rows($conn, "SELECT id FROM tbl_subs_profile WHERE id=? LIMIT 1", 'i', [$subscriberId]);
            $taken = shaven_rows($conn, "SELECT id FROM tbl_sys_users WHERE subscriber_id=? AND sys_level=4 AND id<>? LIMIT 1", 'ii', [$subscriberId, $id]);
            if ($subscriberId <= 0 || !$sub) {
                $error = 'Link a subscriber record to a subscriber login.';
            } elseif ($taken) {
                $error = 'That subscriber already has a portal login.';
            }
        }
        if ($error === '') {
            if ($level !== 4) {
                $subscriberId = 0;
            }
            $subSql = $subscriberId > 0 ? (string)$subscriberId : 'NULL';
            $saved = shaven_exec(
                $conn,
                "UPDATE tbl_sys_users SET sys_user_fullname=?, sys_level=?, status=?, subscriber_id=$subSql WHERE id=?",
                'siii',
                [$fullname, $level, $status, $id]
            );
            if ($password !== '') {
                shaven_exec($conn, "UPDATE tbl_sys_users SET password=? WHERE id=?", 'si', [$password, $id]);
            }
            if ($id === $actor) {
                $_SESSION['fullname'] = $fullname;
                $_SESSION['sys_level'] = $level;
                $_SESSION['subscriber_id'] = $subscriberId;
            }
            shaven_flash_set($saved['ok'] ? 'Account saved.' : 'The account was not saved.', $saved['ok'] ? 'ok' : 'err');
        } else {
            shaven_flash_set($error, 'err');
        }
        shaven_redirect('/pages/admin/users.php');
    }
}

$users = shaven_rows(
    $conn,
    "SELECT u.id, u.username, u.sys_user_fullname, u.sys_level, u.status, u.subscriber_id, u.sys_user_dor, s.s_name
     FROM tbl_sys_users u
     LEFT JOIN tbl_subs_profile s ON s.id=u.subscriber_id
     ORDER BY u.sys_level, u.username"
);
$subs = shaven_rows(
    $conn,
    "SELECT s.id, s.s_name, MIN(u.username) AS username
     FROM tbl_subs_profile s
     LEFT JOIN tbl_sys_users u ON u.subscriber_id=s.id AND u.sys_level=4
     GROUP BY s.id, s.s_name
     ORDER BY s.s_name"
);
shaven_page_open('Accounts', 'Accounts');
?>
<div class="row">
  <div class="col-lg-4">
    <div class="card card-primary">
      <div class="card-header"><h3 class="card-title">New account</h3></div>
      <form method="post">
        <div class="card-body">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="action" value="create">
          <div class="form-group"><label>Name</label><input class="form-control" name="fullname" required></div>
          <div class="form-group"><label>Username</label><input class="form-control" name="username" required></div>
          <div class="form-group"><label>Password</label><input class="form-control" name="password" required></div>
          <div class="form-group">
            <label>Role</label>
            <select class="form-control" name="sys_level">
              <option value="1">Owner</option>
              <option value="2" selected>Admin</option>
              <option value="3">Technician</option>
              <option value="4">Subscriber</option>
            </select>
          </div>
          <div class="form-group">
            <label>Subscriber record</label>
            <select class="form-control" name="subscriber_id">
              <option value="0">Office account, no subscriber link</option>
              <?php foreach ($subs as $sub): ?>
                <option value="<?php echo (int)$sub['id']; ?>">
                  <?php echo h($sub['s_name']); ?><?php echo $sub['username'] ? ' (login: ' . h($sub['username']) . ')' : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Required for a subscriber login. Each subscriber has one portal login.</small>
          </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">Create account</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h3 class="card-title">People who can sign in</h3></div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>Account</th><th>Role and access</th></tr></thead>
          <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td style="min-width:220px;">
                <strong><?php echo h($user['sys_user_fullname']); ?></strong><br>
                <span class="text-muted"><?php echo h($user['username']); ?></span><br>
                <?php echo shaven_role_badge($user['sys_level']); ?>
                <?php if ((int)$user['status'] !== 1): ?><span class="badge badge-secondary">Disabled</span><?php endif; ?>
                <?php if ($user['s_name']): ?>
                  <div class="mt-2"><a href="<?php echo h(shaven_url('pages/tables/subscriber_view.php?uid=' . (int)$user['subscriber_id'])); ?>"><?php echo h($user['s_name']); ?></a></div>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" class="form-row">
                  <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
                  <input type="hidden" name="action" value="save">
                  <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                  <input type="hidden" name="subscriber_id" value="<?php echo (int)$user['subscriber_id']; ?>">
                  <div class="form-group col-md-4"><label>Name</label><input class="form-control" name="fullname" value="<?php echo h($user['sys_user_fullname']); ?>"></div>
                  <div class="form-group col-md-2">
                    <label>Role</label>
                    <select class="form-control" name="sys_level">
                      <?php foreach ([1 => 'Owner', 2 => 'Admin', 3 => 'Technician', 4 => 'Subscriber'] as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (int)$user['sys_level'] === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group col-md-2">
                    <label>Status</label>
                    <select class="form-control" name="status">
                      <option value="1" <?php echo (int)$user['status'] === 1 ? 'selected' : ''; ?>>Active</option>
                      <option value="0" <?php echo (int)$user['status'] !== 1 ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                  </div>
                  <div class="form-group col-md-2"><label>New password</label><input class="form-control" name="password" placeholder="Unchanged"></div>
                  <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-block" type="submit">Save</button></div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php shaven_page_close(); ?>
