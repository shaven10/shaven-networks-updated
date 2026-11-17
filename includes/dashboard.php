<?php
function shaven_dashboard_bundle($conn) {
    $now = new DateTime('now');
    $year = (int)$now->format('Y');
    $month = $now->format('F');
    $monthStart = $now->format('Y-m-01');
    $nextMonth = (clone $now)->modify('first day of next month')->format('Y-m-d');
    $dayStart = (clone $now)->modify('-13 days')->format('Y-m-d');

    $counts = [0 => 0, 1 => 0, 2 => 0];
    foreach (shaven_rows($conn, "SELECT status, COUNT(*) c FROM tbl_subs_profile GROUP BY status") as $row) {
        $counts[(int)$row['status']] = (int)$row['c'];
    }
    $monthRow = shaven_rows(
        $conn,
        "SELECT
            COALESCE(SUM(CASE WHEN status=1 THEN payment_amount END),0) paid,
            COALESCE(SUM(CASE WHEN status=1 THEN 1 END),0) paid_n,
            COALESCE(SUM(CASE WHEN status=0 THEN payment_amount END),0) unpaid,
            COALESCE(SUM(CASE WHEN status=0 THEN 1 END),0) unpaid_n
         FROM tbl_payments WHERE payment_year=? AND payment_month=?",
        'is',
        [$year, $month]
    )[0];
    $overdue = shaven_rows(
        $conn,
        "SELECT COUNT(*) c, COALESCE(SUM(payment_amount),0) amt
         FROM tbl_payments WHERE status=0 AND NOT (payment_year=? AND payment_month=?)",
        'is',
        [$year, $month]
    )[0];
    $reverts = shaven_rows($conn, "SELECT COUNT(*) c FROM tbl_payments WHERE revert=1 AND status=1")[0];
    $installed = shaven_rows(
        $conn,
        "SELECT COUNT(*) c FROM tbl_subs_profile WHERE s_doi>=? AND s_doi<?",
        'ss',
        [$monthStart, $nextMonth]
    )[0];
    $walletRows = shaven_rows(
        $conn,
        "SELECT COALESCE(SUM(a.payment_amount),0) amt
         FROM tbl_payments a
         INNER JOIN tbl_remittances b ON a.id=b.paymentid
         WHERE b.remit='0' AND a.status=1" . (shaven_role_is(1, 2) ? '' : ' AND a.postedby=?'),
        shaven_role_is(1, 2) ? '' : 'i',
        shaven_role_is(1, 2) ? [] : [(int)$_SESSION['usr_id']]
    );
    $months = shaven_rows($conn, "SELECT id, month_name FROM tbl_months ORDER BY id");
    $yearRows = shaven_rows(
        $conn,
        "SELECT payment_month,
            COALESCE(SUM(CASE WHEN status=1 THEN payment_amount END),0) paid,
            COALESCE(SUM(CASE WHEN status=0 THEN payment_amount END),0) unpaid
         FROM tbl_payments WHERE payment_year=? GROUP BY payment_month",
        'i',
        [$year]
    );
    $byMonth = [];
    foreach ($yearRows as $row) {
        $byMonth[$row['payment_month']] = $row;
    }
    $labels = [];
    $paid = [];
    $unpaid = [];
    foreach ($months as $item) {
        $name = $item['month_name'];
        $labels[] = $name;
        $paid[] = (float)($byMonth[$name]['paid'] ?? 0);
        $unpaid[] = (float)($byMonth[$name]['unpaid'] ?? 0);
    }
    $dayRows = shaven_rows(
        $conn,
        "SELECT DATE(payment_date) d, SUM(payment_amount) amt
         FROM tbl_payments
         WHERE status=1 AND payment_date IS NOT NULL AND payment_date>=?
         GROUP BY DATE(payment_date)",
        's',
        [$dayStart . ' 00:00:00']
    );
    $dayMap = [];
    foreach ($dayRows as $row) {
        $dayMap[$row['d']] = (float)$row['amt'];
    }
    $days = [];
    $dayAmounts = [];
    $cursor = new DateTime($dayStart);
    $end = new DateTime('today');
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $days[] = $cursor->format('M j');
        $dayAmounts[] = (float)($dayMap[$key] ?? 0);
        $cursor->modify('+1 day');
    }
    $plans = shaven_rows(
        $conn,
        "SELECT COALESCE(pl.p_name, 'No plan') name, COUNT(*) c
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id=s.plan_id
         WHERE s.status=1
         GROUP BY pl.id, pl.p_name
         ORDER BY pl.id"
    );
    $mediums = shaven_rows(
        $conn,
        "SELECT COALESCE(md.medium_name, 'Unassigned') name, COUNT(*) c
         FROM tbl_subs_profile s
         LEFT JOIN tbl_mediums md ON md.id=s.medium
         WHERE s.status=1
         GROUP BY md.id, md.medium_name
         ORDER BY c DESC"
    );
    $recent = shaven_rows(
        $conn,
        "SELECT p.payment_date, p.payment_amount, p.payment_month, p.payment_year, p.payment_day,
                p.period_start, p.period_end, p.due_date, p.pay_channel, s.s_name, s.s_doi, s.id AS sid
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id=p.payment_uid
         WHERE p.status=1 AND p.payment_date IS NOT NULL
         ORDER BY p.payment_date DESC
         LIMIT 8"
    );
    $unpaidList = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_address, s.s_doi, pl.p_name, p.payment_amount,
                p.payment_month, p.payment_year, p.payment_day, p.period_start, p.period_end, p.due_date
         FROM tbl_payments p
         INNER JOIN tbl_subs_profile s ON s.id=p.payment_uid
         LEFT JOIN tbl_plans pl ON pl.id=s.plan_id
         WHERE p.status=0 AND p.payment_year=? AND p.payment_month=? AND s.status=1",
        'is',
        [$year, $month]
    );
    shaven_sort_by_due($unpaidList);
    $unpaidList = array_slice($unpaidList, 0, 12);
    $inactive = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_address, pl.p_name
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id=s.plan_id
         WHERE s.status=2
         ORDER BY s.s_name
         LIMIT 8"
    );
    $recentInstalls = shaven_rows(
        $conn,
        "SELECT s.id, s.s_name, s.s_address, s.s_doi, pl.p_name
         FROM tbl_subs_profile s
         LEFT JOIN tbl_plans pl ON pl.id=s.plan_id
         ORDER BY s.timestamp DESC
         LIMIT 8"
    );
    return [
        'year' => $year,
        'month' => $month,
        'active' => $counts[1],
        'inactive' => $counts[2],
        'pending' => $counts[0],
        'paid' => (float)$monthRow['paid'],
        'paid_n' => (int)$monthRow['paid_n'],
        'unpaid' => (float)$monthRow['unpaid'],
        'unpaid_n' => (int)$monthRow['unpaid_n'],
        'overdue_n' => (int)$overdue['c'],
        'overdue' => (float)$overdue['amt'],
        'reverts' => (int)$reverts['c'],
        'installed' => (int)$installed['c'],
        'wallet' => (float)($walletRows[0]['amt'] ?? 0),
        'remit_held' => shaven_remit_held($conn, (int)($_SESSION['usr_id'] ?? 0), 1),
        'remit_waiting' => shaven_remit_waiting($conn, (int)($_SESSION['usr_id'] ?? 0)),
        'remit_confirm' => shaven_remit_confirm_totals($conn, (int)($_SESSION['usr_id'] ?? 0)),
        'recent' => $recent,
        'unpaid_list' => $unpaidList,
        'inactive_list' => $inactive,
        'recent_installs' => $recentInstalls,
        'charts' => [
            'months' => $labels,
            'paid' => $paid,
            'unpaid' => $unpaid,
            'days' => $days,
            'dayAmounts' => $dayAmounts,
            'planLabels' => array_column($plans, 'name'),
            'planCounts' => array_map('intval', array_column($plans, 'c')),
            'mediumLabels' => array_column($mediums, 'name'),
            'mediumCounts' => array_map('intval', array_column($mediums, 'c')),
        ],
    ];
}

function shaven_chart_json(array $charts) {
    $GLOBALS['shaven_needs_chart'] = true;
    echo '<script type="application/json" id="dash-data">' . json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}

function shaven_render_staff_dashboard($conn) {
    $data = shaven_dashboard_bundle($conn);
    $subscriberUrl = shaven_url('pages/tables/subscriber_view.php?uid=');
    ?>
    <p class="text-muted">Figures for <?php echo h($data['month'] . ' ' . $data['year']); ?> come from subscriber bills already stored in the billing tables.</p>
    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
          <div class="inner"><h3><?php echo (int)$data['active']; ?></h3><p>Active subscribers</p></div>
          <div class="icon"><i class="fas fa-users"></i></div>
          <a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/reports.php')); ?>">Open subscriber list <i class="fas fa-arrow-circle-right"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
          <div class="inner"><h3><?php echo shaven_peso($data['paid']); ?></h3><p>Collected in <?php echo h($data['month']); ?></p></div>
          <div class="icon"><i class="fas fa-check"></i></div>
          <a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/collections_records.php?view=paid')); ?>"><?php echo (int)$data['paid_n']; ?> posted payments <i class="fas fa-arrow-circle-right"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
          <div class="inner"><h3><?php echo shaven_peso($data['unpaid']); ?></h3><p>Unpaid in <?php echo h($data['month']); ?></p></div>
          <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
          <a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/collections_records.php?view=unpaid')); ?>"><?php echo (int)$data['unpaid_n']; ?> open bills <i class="fas fa-arrow-circle-right"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
          <div class="inner"><h3><?php echo shaven_peso($data['overdue']); ?></h3><p>Older unpaid bills</p></div>
          <div class="icon"><i class="fas fa-history"></i></div>
          <a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/collections_records.php?view=overdue')); ?>"><?php echo (int)$data['overdue_n']; ?> backlog bills <i class="fas fa-arrow-circle-right"></i></a>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Collections, last 14 days</h3></div>
          <div class="card-body"><div style="height:260px"><canvas id="dayChart"></canvas></div></div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title"><?php echo (int)$data['year']; ?> paid and unpaid by month</h3></div>
          <div class="card-body"><div style="height:280px"><canvas id="yearChart"></canvas></div></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Office queue</h3></div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between"><span>Unpaid <?php echo h($data['month']); ?> bills</span><strong><?php echo (int)$data['unpaid_n']; ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Inactive lines</span><strong><?php echo (int)$data['inactive']; ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Pending installs</span><strong><?php echo (int)$data['pending']; ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Installed in <?php echo h($data['month']); ?></span><strong><?php echo (int)$data['installed']; ?></strong></li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="<?php echo h(shaven_url('pages/tables/remittances.php')); ?>"><?php echo shaven_role_is(1, 2) ? 'Cash waiting to be remitted' : 'Your unremitted collections'; ?></a>
                <strong><?php echo shaven_peso($data['wallet']); ?></strong>
              </li>
              <?php if (shaven_role_is(1, 2)): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <a href="<?php echo h(shaven_url('pages/tables/remittances.php')); ?>">Batches waiting for confirmation</a>
                  <strong><?php echo (int)$data['remit_confirm']['batches']; ?> · <?php echo shaven_peso($data['remit_confirm']['amount']); ?></strong>
                </li>
              <?php endif; ?>
              <?php if (shaven_role_is(1, 2)): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <a href="<?php echo h(shaven_url('pages/tables/collections_revert_approval.php')); ?>">Revert requests</a>
                  <span class="badge badge-warning"><?php echo (int)$data['reverts']; ?></span>
                </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Active subscribers by plan</h3></div>
          <div class="card-body"><div style="height:220px"><canvas id="planChart"></canvas></div></div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Active lines by medium</h3></div>
          <div class="card-body"><div style="height:220px"><canvas id="mediumChart"></canvas></div></div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Latest posted payments</h3></div>
          <div class="card-body table-responsive p-0">
            <table class="table table-striped mb-0">
              <thead><tr><th>Date</th><th>Subscriber</th><th>Period</th><th>Due date</th><th>Amount</th><th>Channel</th></tr></thead>
              <tbody>
              <?php foreach (shaven_rows_by_subscriber($data['recent'], 'desc') as $subscriberName => $subscriberRows): ?>
                <?php echo shaven_subscriber_group_row($subscriberName, $subscriberRows, 6); ?>
                <?php foreach ($subscriberRows as $row): ?>
                <?php $recentDue = shaven_due_text($row, $row['s_doi']); ?>
                <tr>
                  <td><?php echo h(shaven_date($row['payment_date'], 'M j, g:i A')); ?></td>
                  <td><a href="<?php echo h($subscriberUrl . (int)$row['sid']); ?>"><?php echo h($row['s_name']); ?></a></td>
                  <td><?php echo h($row['payment_month'] . ' ' . $row['payment_year']); ?></td>
                  <td><?php echo $recentDue !== '' ? '<span class="shaven-due-date">' . h($recentDue) . '</span>' : '—'; ?></td>
                  <td><?php echo shaven_peso($row['payment_amount']); ?></td>
                  <td><?php echo h(shaven_channel_label(1, $row['pay_channel'])); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Unpaid in <?php echo h($data['month']); ?></h3></div>
          <div class="card-body table-responsive p-0">
            <table class="table table-striped mb-0">
              <thead><tr><th>Subscriber</th><th>Plan</th><th>Due date</th><th class="text-right">Amount</th></tr></thead>
              <tbody>
              <?php foreach (shaven_rows_by_subscriber($data['unpaid_list']) as $subscriberName => $subscriberRows): ?>
                <?php echo shaven_subscriber_group_row($subscriberName, $subscriberRows, 4); ?>
                <?php foreach ($subscriberRows as $row): ?>
                <?php $openDue = shaven_due_text($row, $row['s_doi']); ?>
                <tr>
                  <td><a href="<?php echo h($subscriberUrl . (int)$row['id']); ?>"><?php echo h($row['s_name']); ?></a></td>
                  <td><?php echo h($row['p_name']); ?></td>
                  <td><?php echo $openDue !== '' ? '<span class="shaven-due-date">' . h($openDue) . '</span>' : '—'; ?></td>
                  <td class="text-right"><?php echo shaven_peso($row['payment_amount']); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer"><a href="<?php echo h(shaven_url('pages/tables/collections_records.php?view=unpaid')); ?>">Open the full unpaid list</a></div>
        </div>
      </div>
    </div>
    <?php
    shaven_chart_json($data['charts']);
}

function shaven_render_tech_dashboard($conn) {
    $data = shaven_dashboard_bundle($conn);
    $subscriberUrl = shaven_url('pages/tables/subscriber_view.php?uid=');
    ?>
    <p class="text-muted">Service snapshot for <?php echo h($data['month'] . ' ' . $data['year']); ?>. Open a name to see the line, plan, and bills.</p>
    <div class="row">
      <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3><?php echo (int)$data['active']; ?></h3><p>Active lines</p></div><div class="icon"><i class="fas fa-wifi"></i></div><a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/reports.php')); ?>">Subscriber list</a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-danger"><div class="inner"><h3><?php echo (int)$data['inactive']; ?></h3><p>Inactive lines</p></div><div class="icon"><i class="fas fa-plug"></i></div><span class="small-box-footer">Ready for a field visit</span></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3><?php echo (int)$data['pending']; ?></h3><p>Pending</p></div><div class="icon"><i class="fas fa-hourglass-half"></i></div><span class="small-box-footer">Waiting for activation</span></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3><?php echo (int)$data['unpaid_n']; ?></h3><p>Unpaid in <?php echo h($data['month']); ?></p></div><div class="icon"><i class="fas fa-clipboard-list"></i></div><a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/collections_records.php?view=unpaid')); ?>">Open unpaid list</a></div></div>
    </div>
    <div class="row">
      <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo shaven_peso($data['remit_held']['amount']); ?></h3><p>Cash you are holding</p></div><div class="icon"><i class="fas fa-wallet"></i></div><a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/remittances.php')); ?>"><?php echo (int)$data['remit_held']['count']; ?> not yet submitted</a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3><?php echo shaven_peso($data['remit_waiting']['amount']); ?></h3><p>Submitted for confirmation</p></div><div class="icon"><i class="fas fa-paper-plane"></i></div><a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/remittances.php')); ?>"><?php echo (int)$data['remit_waiting']['count']; ?> waiting for an admin</a></div></div>
      <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3><i class="fas fa-cash-register"></i></h3><p>Post a collection</p></div><div class="icon"><i class="fas fa-money-bill-wave"></i></div><a class="small-box-footer" href="<?php echo h(shaven_url('pages/tables/collections.php')); ?>">Open post payment</a></div></div>
    </div>
    <div class="row">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Unpaid active lines this month</h3></div>
          <div class="card-body table-responsive p-0">
            <table class="table table-striped mb-0">
              <thead><tr><th>Subscriber</th><th>Address</th><th>Plan</th><th>Due date</th><th>Bill</th></tr></thead>
              <tbody>
              <?php foreach (shaven_rows_by_subscriber($data['unpaid_list']) as $subscriberName => $subscriberRows): ?>
                <?php echo shaven_subscriber_group_row($subscriberName, $subscriberRows, 5); ?>
                <?php foreach ($subscriberRows as $row): ?>
                <?php $techDue = shaven_due_text($row, $row['s_doi']); ?>
                <tr>
                  <td><a href="<?php echo h($subscriberUrl . (int)$row['id']); ?>"><?php echo h($row['s_name']); ?></a></td>
                  <td><?php echo h($row['s_address']); ?></td>
                  <td><?php echo h($row['p_name']); ?></td>
                  <td><?php echo $techDue !== '' ? '<span class="shaven-due-date">' . h($techDue) . '</span>' : '—'; ?></td>
                  <td><?php echo shaven_peso($row['payment_amount']); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Inactive lines</h3></div>
          <div class="card-body table-responsive p-0">
            <table class="table table-striped mb-0">
              <thead><tr><th>Subscriber</th><th>Address</th><th>Last plan</th></tr></thead>
              <tbody>
              <?php foreach ($data['inactive_list'] as $row): ?>
                <tr>
                  <td><a href="<?php echo h($subscriberUrl . (int)$row['id']); ?>"><?php echo h($row['s_name']); ?></a></td>
                  <td><?php echo h($row['s_address']); ?></td>
                  <td><?php echo h($row['p_name']); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Newest records</h3></div>
          <ul class="list-group list-group-flush">
            <?php foreach ($data['recent_installs'] as $row): ?>
              <li class="list-group-item">
                <a href="<?php echo h($subscriberUrl . (int)$row['id']); ?>"><?php echo h($row['s_name']); ?></a>
                <div class="text-muted small"><?php echo h($row['p_name']); ?> · installed <?php echo h(shaven_date($row['s_doi'])); ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Lines by medium</h3></div>
          <div class="card-body"><div style="height:220px"><canvas id="mediumChart"></canvas></div></div>
        </div>
      </div>
    </div>
    <?php
    shaven_chart_json($data['charts']);
}

function shaven_rows_by_subscriber(array $rows, $dueDirection = 'asc') {
    $groups = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['s_name'] ?? ''));
        if ($name === '') {
            $name = 'Unnamed subscriber';
        }
        if (!isset($groups[$name])) {
            $groups[$name] = [];
        }
        $groups[$name][] = $row;
    }
    uksort($groups, 'strnatcasecmp');
    $direction = strtolower((string)$dueDirection) === 'desc' ? -1 : 1;
    foreach ($groups as &$items) {
        usort($items, function ($left, $right) use ($direction) {
            return $direction * strcmp(
                shaven_due_sort_value($left, $left['s_doi'] ?? ''),
                shaven_due_sort_value($right, $right['s_doi'] ?? '')
            );
        });
    }
    unset($items);
    return $groups;
}

function shaven_subscriber_group_row($name, array $rows, $colspan) {
    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float)($row['payment_amount'] ?? 0);
    }
    $count = count($rows);
    $label = $count === 1 ? 'record' : 'records';
    return '<tr class="shaven-bill-group"><td colspan="' . (int)$colspan . '"><div class="shaven-bill-group"><span class="shaven-group-name">'
        . h($name) . '</span><span class="shaven-group-meta">' . $count . ' ' . $label . ' · ' . shaven_peso($total) . '</span></div></td></tr>';
}

function shaven_render_subscriber_dashboard($conn) {
    require_once __DIR__ . '/account_view.php';
    $subscriberId = (int)($_SESSION['subscriber_id'] ?? 0);
    if ($subscriberId <= 0) {
        echo '<div class="alert alert-warning">This login is not linked to a subscriber record yet. The owner can connect it from the subscriber page.</div>';
        return;
    }
    $account = shaven_subscriber_account($conn, $subscriberId);
    if (!$account) {
        echo '<div class="alert alert-warning">The linked subscriber record is no longer in the billing file.</div>';
        return;
    }
    $profile = $account['profile'];
    ?>
    <div class="mb-3">
      <a class="btn btn-primary" href="<?php echo h(shaven_url('pages/account/pay.php')); ?>"><i class="fas fa-money-bill-wave"></i> Pay bill</a>
      <a class="btn btn-default" href="<?php echo h(shaven_url('pages/account/plan.php')); ?>"><i class="fas fa-exchange-alt"></i> Change plan</a>
      <a class="btn btn-default" href="<?php echo h(shaven_url('pages/account/index.php')); ?>"><i class="fas fa-id-card"></i> Full account</a>
    </div>
    <?php
    shaven_render_account_stats($account);
    shaven_render_account_profile($account);
    shaven_render_open_bills($account, false);
}
