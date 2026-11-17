<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);

$userId = (int)($_SESSION['usr_id'] ?? 0);

if (isset($_GET['feed'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $menu = shaven_notify_menu($conn, $userId);
    echo json_encode(['unread' => (int)$menu['unread'], 'html' => $menu['html']], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['open'])) {
    $row = shaven_notify_mark($conn, $userId, (int)$_GET['open']);
    if (!$row) {
        shaven_redirect('/pages/account/notifications.php');
    }
    $path = shaven_notify_safe_path($row['link'] ?? '');
    if (strpos($path, 'pages/account/notifications.php') === 0) {
        shaven_redirect('/pages/account/notifications.php');
    }
    shaven_redirect('/' . $path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The form expired. Submit it again.', 'err');
        shaven_redirect('/pages/account/notifications.php');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'read_all') {
        shaven_notify_mark_all($conn, $userId);
        shaven_flash_set('All notifications are marked read.');
    } elseif ($action === 'read') {
        $row = shaven_notify_mark($conn, $userId, (int)($_POST['id'] ?? 0));
        shaven_flash_set($row ? 'Notification marked read.' : 'That notification was not found.', $row ? 'ok' : 'err');
    } elseif ($action === 'send') {
        $sent = shaven_notify_announce($conn, $userId, $_POST['audience'] ?? '', $_POST['title'] ?? '', $_POST['body'] ?? '');
        if ($sent['ok']) {
            $sentCount = (int)$sent['sent'];
            shaven_flash_set('Sent to ' . $sentCount . ' account' . ($sentCount === 1 ? '' : 's') . '.');
        } else {
            shaven_flash_set($sent['error'], 'err');
        }
    } else {
        shaven_flash_set('That action is not available.', 'err');
    }
    shaven_redirect('/pages/account/notifications.php');
}

$unreadOnly = (($_GET['box'] ?? '') === 'unread');
$unread = shaven_notify_unread_count($conn, $userId);
$items = shaven_notify_list($conn, $userId, $unreadOnly, 80);
$canSend = shaven_role_is(1, 2);
$pageUrl = shaven_url('pages/account/notifications.php');
$unreadUrl = shaven_url('pages/account/notifications.php?box=unread');

shaven_page_open('Notifications', 'Notifications');
?>
<p class="text-muted">Payments, remittances, bills, plan changes, and office notices land here for every role. The bell in the header stays with you on each page.</p>
<div class="d-flex flex-wrap align-items-center mb-3">
  <div class="btn-group mr-2 mb-2">
    <a class="btn btn-sm <?php echo $unreadOnly ? 'btn-default' : 'btn-primary'; ?>" href="<?php echo h($pageUrl); ?>">All</a>
    <a class="btn btn-sm <?php echo $unreadOnly ? 'btn-primary' : 'btn-default'; ?>" href="<?php echo h($unreadUrl); ?>">Unread<?php echo $unread > 0 ? ' (' . (int)$unread . ')' : ''; ?></a>
  </div>
  <?php if ($unread > 0): ?>
    <form method="post" class="mb-2">
      <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
      <input type="hidden" name="action" value="read_all">
      <button class="btn btn-sm btn-default" type="submit">Mark all read</button>
    </form>
  <?php endif; ?>
</div>
<div class="row">
  <div class="<?php echo $canSend ? 'col-lg-8' : 'col-lg-10'; ?>">
    <?php if (!$items): ?>
      <div class="card">
        <div class="card-body text-muted">
          <?php echo $unreadOnly ? 'You have no unread notifications.' : 'No notifications yet. Activity on your account will show up here.'; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
      <?php
        $isUnread = (int)$item['is_read'] === 0;
        $target = shaven_notify_safe_path($item['link'] ?? '');
        $opensElsewhere = strpos($target, 'pages/account/notifications.php') !== 0;
      ?>
      <article class="shaven-note<?php echo $isUnread ? ' is-unread' : ''; ?>">
        <i class="<?php echo h(shaven_notify_icon($item['kind'])); ?> shaven-note-icon"></i>
        <div class="shaven-note-body">
          <div class="shaven-note-meta"><?php echo h(shaven_notify_kind_label($item['kind'])); ?> · <?php echo h(shaven_notify_when($item['created_at'])); ?></div>
          <a class="shaven-note-title" href="<?php echo h(shaven_notify_open_url($item['id'])); ?>"><?php echo h($item['title']); ?></a>
          <?php if (trim((string)$item['body']) !== ''): ?>
            <p><?php echo h($item['body']); ?></p>
          <?php endif; ?>
          <div class="shaven-note-actions">
            <?php if ($opensElsewhere): ?>
              <a class="btn btn-sm btn-primary" href="<?php echo h(shaven_notify_open_url($item['id'])); ?>">Open</a>
            <?php endif; ?>
            <?php if ($isUnread): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
                <input type="hidden" name="action" value="read">
                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                <button class="btn btn-sm btn-default" type="submit">Mark read</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($canSend): ?>
    <div class="col-lg-4">
      <div class="card card-primary">
        <div class="card-header"><h3 class="card-title">Send a notice</h3></div>
        <form method="post">
          <div class="card-body">
            <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
            <input type="hidden" name="action" value="send">
            <div class="form-group">
              <label for="notify-audience">Who receives it</label>
              <select class="form-control" id="notify-audience" name="audience">
                <option value="all">Everyone</option>
                <option value="1">Owners</option>
                <option value="2">Admins</option>
                <option value="3">Technicians</option>
                <option value="4">Subscribers</option>
              </select>
            </div>
            <div class="form-group">
              <label for="notify-title">Title</label>
              <input class="form-control" id="notify-title" name="title" maxlength="160" required>
            </div>
            <div class="form-group mb-0">
              <label for="notify-body">Message</label>
              <textarea class="form-control" id="notify-body" name="body" rows="5" maxlength="500" required></textarea>
            </div>
            <small class="form-text text-muted">Active accounts in that group receive it. Your own account is left out.</small>
          </div>
          <div class="card-footer"><button class="btn btn-primary" type="submit">Send notice</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
shaven_page_close();
