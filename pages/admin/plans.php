<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/layout.php';
shaven_web_boot(true);
if (!shaven_role_is(1, 2)) {
    shaven_redirect('/index.php?denied=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shaven_csrf_ok()) {
        shaven_flash_set('The plan form expired. Submit it again.', 'err');
        shaven_redirect('/pages/admin/plans.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $saved = shaven_save_plan(
            $conn,
            (int)($_POST['plan_id'] ?? 0),
            $_POST['p_name'] ?? '',
            $_POST['mbps'] ?? 0,
            $_POST['amount'] ?? 0
        );
        if ($saved['ok']) {
            $message = (int)($_POST['plan_id'] ?? 0) > 0 ? 'Plan saved.' : 'Plan added. New subscribers and plan changes can avail it.';
            if ((int)$saved['bills'] > 0) {
                $message .= ' ' . (int)$saved['bills'] . ' open ' . date('F') . ' bill' . ((int)$saved['bills'] === 1 ? '' : 's') . ' now use the new monthly amount.';
            }
            shaven_flash_set($message);
            shaven_redirect('/pages/admin/plans.php?plan=' . (int)$saved['id']);
        }
        shaven_flash_set($saved['error'], 'err');
        shaven_redirect('/pages/admin/plans.php');
    }
    if ($action === 'delete') {
        $removed = shaven_delete_plan($conn, (int)($_POST['plan_id'] ?? 0));
        shaven_flash_set($removed['ok'] ? 'Plan removed from the catalog.' : $removed['error'], $removed['ok'] ? 'ok' : 'err');
        shaven_redirect('/pages/admin/plans.php');
    }
    if ($action === 'bind') {
        $subscriberId = (int)($_POST['subscriber_id'] ?? 0);
        $planId = (int)($_POST['plan_id'] ?? 0);
        $result = shaven_change_plan($conn, $subscriberId, $planId, (int)$_SESSION['usr_id'], 'Availed plan set by the office');
        if ($result === true) {
            shaven_flash_set('The subscriber is now bound to the selected plan. This month\'s open bill uses that plan\'s monthly amount.');
            shaven_redirect('/pages/admin/plans.php?plan=' . $planId);
        }
        shaven_flash_set($result, 'err');
        shaven_redirect('/pages/admin/plans.php?plan=' . $planId);
    }
}

$plans = shaven_plan_catalog($conn);
$focus = (int)($_GET['plan'] ?? 0);
$focusPlan = null;
foreach ($plans as $plan) {
    if ((int)$plan['id'] === $focus) {
        $focusPlan = $plan;
        break;
    }
}
$subscribers = [];
if ($focusPlan) {
    $subscribers = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_address, s.status, s.s_doi
         FROM tbl_subs_profile s
         WHERE s.plan_id=?
         ORDER BY s.s_doi ASC, s.s_name ASC",
        'i',
        [$focus]
    );
}
$allSubscribers = shaven_rows(
    $conn,
    "SELECT s.id, s.s_name, s.plan_id, pl.p_name
     FROM tbl_subs_profile s
     LEFT JOIN tbl_plans pl ON pl.id = s.plan_id
     ORDER BY s.s_name"
);
shaven_page_open('Plans', 'Plans');
?>
<p>Each subscriber stays bound to one availed plan. The account, the speed, and new bills read that plan from this catalog. Changing a monthly amount also updates this month's open bills for subscribers already on that plan. Paid months stay at the amount that was billed.</p>
<div class="row">
  <div class="col-lg-4">
    <div class="card card-primary">
      <div class="card-header"><h3 class="card-title">Add a plan</h3></div>
      <form method="post">
        <div class="card-body">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="plan_id" value="0">
          <div class="form-group"><label>Plan name</label><input class="form-control" name="p_name" required placeholder="Plan 800"></div>
          <div class="form-group"><label>Speed (Mbps)</label><input class="form-control" name="mbps" type="number" min="0.1" step="0.1" required></div>
          <div class="form-group"><label>Monthly amount</label><input class="form-control" name="amount" type="number" min="1" step="0.01" required></div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">Add plan</button></div>
      </form>
    </div>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Bind a subscriber</h3></div>
      <form method="post">
        <div class="card-body">
          <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
          <input type="hidden" name="action" value="bind">
          <div class="form-group">
            <label>Subscriber</label>
            <select class="form-control" name="subscriber_id" required>
              <option value="">Choose a subscriber</option>
              <?php foreach ($allSubscribers as $sub): ?>
                <option value="<?php echo (int)$sub['id']; ?>"><?php echo h($sub['s_name'] . ' — ' . ($sub['p_name'] ?: 'No plan')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Availed plan</label>
            <select class="form-control" name="plan_id" required>
              <?php foreach ($plans as $plan): ?>
                <option value="<?php echo (int)$plan['id']; ?>" <?php echo $focus === (int)$plan['id'] ? 'selected' : ''; ?>>
                  <?php echo h($plan['p_name'] . ' · ' . shaven_speed_label($plan['p_speed_kb']) . ' · ' . number_format((float)$plan['p_monthly_amount'], 2)); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">Save availed plan</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <?php foreach ($plans as $plan): ?>
      <div class="card <?php echo $focus === (int)$plan['id'] ? 'card-primary' : ''; ?>">
        <div class="card-header">
          <h3 class="card-title">
            <a href="<?php echo h(shaven_url('pages/admin/plans.php?plan=' . (int)$plan['id'])); ?>"><?php echo h($plan['p_name']); ?></a>
          </h3>
          <div class="card-tools"><span class="badge badge-info"><?php echo (int)$plan['subscribers']; ?> subscriber<?php echo (int)$plan['subscribers'] === 1 ? '' : 's'; ?></span></div>
        </div>
        <form method="post">
          <div class="card-body">
            <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
            <div class="form-row">
              <div class="form-group col-md-4"><label>Name</label><input class="form-control" name="p_name" value="<?php echo h($plan['p_name']); ?>" required></div>
              <div class="form-group col-md-3"><label>Mbps</label><input class="form-control" name="mbps" type="number" min="0.1" step="0.1" value="<?php echo h(rtrim(rtrim(number_format(((int)$plan['p_speed_kb']) / 1000, 1, '.', ''), '0'), '.')); ?>" required></div>
              <div class="form-group col-md-3"><label>Monthly</label><input class="form-control" name="amount" type="number" min="1" step="0.01" value="<?php echo h(number_format((float)$plan['p_monthly_amount'], 2, '.', '')); ?>" required></div>
              <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-block" type="submit">Save</button></div>
            </div>
          </div>
        </form>
        <?php if ((int)$plan['subscribers'] === 0): ?>
          <div class="card-footer">
            <form method="post" onsubmit="return confirm('Remove this plan from the catalog?');">
              <input type="hidden" name="csrf" value="<?php echo h(shaven_csrf_token()); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
              <button class="btn btn-danger btn-sm" type="submit">Remove unused plan</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php if ($focusPlan): ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title">Subscribers who availed <?php echo h($focusPlan['p_name']); ?></h3></div>
    <div class="card-body table-responsive p-0">
      <table class="table table-striped mb-0">
        <thead><tr><th>Installed</th><th>Subscriber</th><th>Address</th><th>Status</th><th>Bound plan</th></tr></thead>
        <tbody>
        <?php if (!$subscribers): ?>
          <tr><td colspan="5">No subscriber is bound to this plan yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($subscribers as $sub): ?>
          <tr>
            <td><?php echo shaven_date($sub['s_doi'], 'F j, Y'); ?></td>
            <td><a href="<?php echo h(shaven_url('pages/tables/subscriber_view.php?uid=' . (int)$sub['id'])); ?>"><?php echo h($sub['s_name']); ?></a></td>
            <td><?php echo h($sub['s_address']); ?></td>
            <td><?php echo shaven_status_badge($sub['status']); ?></td>
            <td><?php echo h($focusPlan['p_name'] . ' · ' . shaven_speed_label($focusPlan['p_speed_kb']) . ' · ' . number_format((float)$focusPlan['p_monthly_amount'], 2)); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php shaven_page_close(); ?>
