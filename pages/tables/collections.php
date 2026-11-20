<?php
require_once __DIR__ . '/../../includes/guard.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if(!isset($_SESSION['usr_id']))
{
	header("location:login.php");
}
$sessionID=$_SESSION['usr_id'];

  //date of today
  $datenow=date_create('Asia/Taipei');
  $DateofToday= date_format($datenow,"m-d-Y");
  $DofToday= date_format($datenow,"d");
  $MonthofToday= date_format($datenow,"F");
  //end

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_desk'])) {
      $deskUid = (int)($_POST['uid'] ?? 0);
      $back = '/pages/tables/collections.php' . ($deskUid > 0 ? '?uid=' . $deskUid : '') . '#pay-desk';
      if (!shaven_csrf_ok()) {
          shaven_flash_set('The payment form expired. Submit it again.', 'err');
          shaven_redirect($back);
      }
      $posted = shaven_counter_collect(
          $conn,
          $deskUid,
          $_POST['bill_ids'] ?? [],
          (int)($_POST['advance_months'] ?? 0),
          (int)$_SESSION['usr_id'],
          $_POST['advance_amount'] ?? 0,
          $_POST['pay_medium'] ?? 'cash'
      );
      if (!$posted['ok']) {
          shaven_flash_set($posted['error'], 'err');
          shaven_redirect($back);
      }
      $count = count($posted['receipts']);
      $summary = $posted['summary'] ?? ('Posted ' . $count . ' payment' . ($count === 1 ? '' : 's') . '.');
      $summary .= ' Submit the cash in a remittance batch. An admin confirms the batch.';
      $receiptIds = [];
      foreach ($posted['receipts'] as $receiptId) {
          $receiptId = (int)$receiptId;
          if ($receiptId > 0) {
              $receiptIds[] = $receiptId;
          }
      }
      shaven_flash_set($summary, 'ok');
      if ($receiptIds) {
          shaven_redirect('/pages/tables/receipt.php?ids=' . implode(',', $receiptIds));
      }
      shaven_redirect($back);
  }
  $payDesk = shaven_payment_desk($conn, (int)($_GET['uid'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SHAVEN Networks | Reports</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../../plugins/fontawesome-free/css/all.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="../../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../../dist/css/shaven-records.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../../dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php require __DIR__ . '/../../includes/topnav.php'; ?>

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo h(shaven_url('index.php')); ?>" class="brand-link">
      <img src="../../dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">SHAVEN Networks</span>
    </a>
    <?php 
        $syslvl= $_SESSION['sys_level'];
       if ($syslvl==1){
        include('../../navigation.sub.php');
       }elseif($syslvl==2){
        include('../../navigation.sub_collector.php');
       }elseif($syslvl==3){
        include('../../navigation.sub_client.php');
       }else{
        echo 'erro on selecting navigation';
       }
        
        
        ?> 
  
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
          <h1>Collection: <?php echo $MonthofToday; ?></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Collection Report</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <?php
              require_once __DIR__ . '/../../includes/payment_desk_view.php';
              shaven_render_payment_desk($payDesk);
              $deskSubscriberId = (int)($payDesk['requested_id'] ?? 0);
              $showAllOpenBills = $deskSubscriberId <= 0 || empty($payDesk['profile']);
            ?>
            <?php if ($showAllOpenBills): ?>
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Open bills</h3>
              </div>
              <div class="card-body pb-0">
                <p class="mb-0">The oldest open bill for each subscriber opens in the payment desk. Later bills wait until that earlier bill is posted.</p>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <table id="example1" class="table table-bordered table-striped" data-count-by="subscriber">
                  <thead>
                  <tr>
                
               
                    <th>Name</th>
                    <th>Billing month</th>
                    <th>Billing period</th>
                      <th>Due date</th>
                    
                    <th>(Php)</th><!--
                    <th colspan='2'>Date of Installation</th>-->
                  <!--<th>Plan</th>-->
                    <th>Status</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php
                      include('../../dbconnect.php');

                     


                  // $sql = "SELECT payment_date,c.id as pid,c.status,payment_year,c.period_start,c.period_end,c.due_date,payment_day,payment_month,p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b,tbl_payments as c where payment_uid=a.id and a.plan_id=b.id and a.status='1' and c.status='0'";
                  $sql = "SELECT payment_year,payment_amount,payment_date,c.id as pid,c.status as stat,payment_year,c.period_start,c.period_end,c.due_date,payment_day,payment_month,p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b,tbl_payments as c where payment_uid=a.id and a.plan_id=b.id and a.status='1' and c.status='0' ";
                  
                  $result = $conn->query($sql);
                    $queue = [];
                    if ($result->num_rows > 0) {
                      while($row = $result->fetch_assoc()) {
                        $queue[] = $row;
                      }
                    }
                    $payable = [];
                    $groupedBills = [];
                    foreach ($queue as $queueRow) {
                      $groupedBills[(int)$queueRow['id']][] = $queueRow;
                    }
                    foreach ($groupedBills as $subscriberBills) {
                      usort($subscriberBills, function ($left, $right) {
                        $byDue = strcmp(shaven_due_sort_value($left, $left['s_doi'] ?? ''), shaven_due_sort_value($right, $right['s_doi'] ?? ''));
                        if ($byDue !== 0) {
                          return $byDue;
                        }
                        return (int)$left['pid'] <=> (int)$right['pid'];
                      });
                      $payable[(int)$subscriberBills[0]['pid']] = true;
                    }
                    if ($queue) {
                      foreach ($queue as $row) {
                        $periodMeta = shaven_bill_period($row, $row['s_doi'] ?? '');
                        $periodOrder = ($periodMeta && !empty($periodMeta['start'])) ? $periodMeta['start'] : '9999-12-31';
                        $periodLabel = shaven_bill_period_label($row, $row['s_doi'] ?? '');
                      echo "<tr>
                     
                
                        <td>".$row['s_name']."</td>
                        ".shaven_billing_month_cell($row)."
                        <td class=\"shaven-period\" data-order=\"".h($periodOrder)."\">".h($periodLabel)."</td>
                         ".shaven_due_cell($row)."
                       
                     
                        <td>".$row['payment_amount']."</td>";
                        if (!empty($payable[(int)$row['pid']])) {
                          echo '<td><span class="badge badge-warning">Unpaid</span> <a class="badge badge-success" href="collections.php?uid='.(int)$row['id'].'#pay-desk">Pay bills</a></td>';
                        } else {
                          echo '<td><span class="badge badge-warning">Unpaid</span> <span class="badge badge-secondary">Pay earlier bill first</span></td>';
                        }
                        echo "</tr>";
                      }
                    } else {
                      echo "0 results";
                    }
                  
                    $conn->close();
                     
                  ?>

                  </tbody>
                  <tfoot>
                  <tr>
                 
               
                
                  <th> </th>
                    <th></th>
                    <th></th>
                <!--    <th colspan='2'>Date of Installation</th>-->
                        <th></th>
                    <th></th>
                  </tr>
                  </tfoot>
                </table>
              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->
            <?php endif; ?>
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->
      </div>
      <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
  <footer class="main-footer">
    <div class="float-right d-none d-sm-block">
      <b>Version</b> 3.2.0
    </div>
    <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="../../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="../../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../../plugins/datatables-rowgroup/js/dataTables.rowGroup.min.js"></script>
<script src="../../dist/js/shaven-groups.js?v=2"></script>
<script src="../../dist/js/shaven-records.js"></script>
<script src="../../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../../plugins/jszip/jszip.min.js"></script>
<script src="../../plugins/pdfmake/pdfmake.min.js"></script>
<script src="../../plugins/pdfmake/vfs_fonts.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../../plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- AdminLTE App -->
<script src="../../dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="../../dist/js/demo.js"></script>
<!-- Page specific script -->
<script>
  $(function () {
    if ($("#example1").length) {
      $("#example1").DataTable({
        footerCallback: function (row, data, start, end, display) {
              var api = this.api();
   
              // Remove the formatting to get integer data for summation
              var intVal = function (i) {
                  return typeof i === 'string' ? i.replace(/[\Php,]/g, '') * 1 : typeof i === 'number' ? i : 0;
              };
   
              // Total over all pages
              total = api
                  .column(3)
                  .data()
                  .reduce(function (a, b) {
                      return intVal(a) + intVal(b);
                  }, 0);
   
              // Total over this page
              pageTotal = api
                  .column(3, { page: 'current' })
                  .data()
                  .reduce(function (a, b) {
                      return intVal(a) + intVal(b);
                  }, 0);
   
              // Update footer
              $(api.column(3).footer()).html('&#x20B1;' + pageTotal + ' (&#x20B1;' + total + ' total)');
          },
    
        "responsive": true, "lengthChange": false, "autoWidth": false,
        "columnDefs": [
          { "responsivePriority": 1, "targets": 0 },
          { "responsivePriority": 2, "targets": 1 },
          { "responsivePriority": 4, "targets": 2 },
          { "responsivePriority": 3, "targets": 3 },
          { "responsivePriority": 5, "targets": 4 }
        ],
        "buttons": []
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    }
    var payForm = document.getElementById('pay-desk-form');
    if (payForm) {
      var boxes = Array.prototype.slice.call(payForm.querySelectorAll('.shaven-pay-bill'));
      var advance = payForm.querySelector('[name="advance_months"]');
      var amountInput = payForm.querySelector('[name="advance_amount"]');
      var mediumInput = payForm.querySelector('[name="pay_medium"]');
      var amountPreview = document.getElementById('amount-preview');
      var previewWrap = document.getElementById('post-preview-wrap');
      var totalNode = document.getElementById('pay-desk-total');
      var note = document.getElementById('advance-note');
      var confirmModal = $('#pay-desk-confirm');
      var confirmSummary = document.getElementById('pay-desk-confirm-summary');
      var confirmMonths = document.getElementById('pay-desk-confirm-months');
      var confirmSubmit = document.getElementById('pay-desk-confirm-submit');
      var monthly = parseFloat(payForm.getAttribute('data-monthly') || '0');
      var subscriberName = payForm.getAttribute('data-subscriber') || 'Subscriber';
      var allowSubmit = false;
      var openBills = [];
      var advancePeriods = [];
      try { openBills = JSON.parse(payForm.getAttribute('data-open') || '[]'); } catch (error) { openBills = []; }
      try { advancePeriods = JSON.parse(payForm.getAttribute('data-advances') || '[]'); } catch (error) { advancePeriods = []; }
      function money(cents) {
        return (cents / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function cents(value) {
        return Math.round((parseFloat(value) || 0) * 100);
      }
      function allChecked() {
        return boxes.length === 0 || boxes.every(function (box) { return box.checked; });
      }
      function amountCents() {
        if (!amountInput || amountInput.disabled) return 0;
        var raw = String(amountInput.value || '').trim();
        if (!/^\d+(\.\d{1,2})?$/.test(raw)) return 0;
        return cents(raw);
      }
      function monthTitle(entry) {
        var month = String(entry.month || '').trim();
        var label = String(entry.label || '').trim();
        var due = String(entry.due || '').trim();
        var title = month || label || 'Billing month';
        if (label && month && label !== month) title += ' · ' + label;
        if (due) title += ' · due ' + due;
        return title;
      }
      function setPreview(lines) {
        if (!amountPreview || !previewWrap) return;
        amountPreview.innerHTML = lines.map(function (line) { return '<li>' + line + '</li>'; }).join('');
        previewWrap.hidden = lines.length === 0;
      }
      function amountLines(cashCents) {
        var left = cashCents;
        var monthlyCents = cents(monthly);
        var lines = [];
        openBills.forEach(function (bill) {
          if (left <= 0) return;
          var billCents = cents(bill.amount);
          if (billCents < 1) return;
          var applied = Math.min(left, billCents);
          var remain = billCents - applied;
          left -= applied;
          var line = monthTitle(bill) + ': ₱' + money(applied) + ' posted';
          if (remain > 0) line += '. ₱' + money(remain) + ' remains open';
          else if (left > 0) line += '. Balance forwarded to the next month';
          lines.push(line);
        });
        advancePeriods.forEach(function (period) {
          if (left <= 0 || monthlyCents < 1) return;
          var applied = Math.min(left, monthlyCents);
          var remain = monthlyCents - applied;
          left -= applied;
          var line = monthTitle(period) + ': ₱' + money(applied) + ' posted';
          if (remain > 0) line += '. ₱' + money(remain) + ' remains open';
          else if (left > 0) line += '. Balance forwarded to the next month';
          lines.push(line);
        });
        return lines;
      }
      function monthLines(months) {
        var lines = [];
        boxes.forEach(function (box, index) {
          if (!box.checked || box.disabled) return;
          var bill = openBills[index] || {};
          var amount = cents(box.getAttribute('data-amount') || bill.amount || 0);
          lines.push(monthTitle(bill) + ': ₱' + money(amount) + ' posted');
        });
        for (var i = 0; i < months && i < advancePeriods.length; i++) {
          var period = advancePeriods[i];
          lines.push(monthTitle(period) + ': ₱' + money(cents(period.amount || monthly)) + ' posted');
        }
        return lines;
      }
      function selectedMonths() {
        if (!advance || advance.disabled) return 0;
        var months = parseInt(advance.value, 10) || 0;
        if (months < 0) months = 0;
        if (months > 12) months = 12;
        return months;
      }
      function selectedTotal(cash, months) {
        if (cash > 0) return cash / 100;
        var total = months * monthly;
        boxes.forEach(function (box) {
          if (box.checked && !box.disabled) total += parseFloat(box.getAttribute('data-amount') || '0');
        });
        return total;
      }
      function mediumLabel() {
        if (!mediumInput) return 'Cash';
        var value = String(mediumInput.value || 'cash').toLowerCase();
        if (value === 'gcash') return 'GCash';
        return 'Cash';
      }
      function syncPayDesk() {
        var cash = amountCents();
        var amountMode = cash > 0;
        boxes.forEach(function (box) { box.disabled = amountMode; });
        var ready = allChecked();
        var months = 0;
        if (advance) {
          advance.disabled = amountMode || !ready;
          if (amountMode || !ready) {
            advance.value = '0';
          }
          months = selectedMonths();
        }
        if (amountInput) amountInput.disabled = months > 0;
        var lines = amountMode
          ? amountLines(cash)
          : (months > 0 || boxes.some(function (box) { return box.checked && !box.disabled; }) ? monthLines(months) : []);
        setPreview(lines);
        if (note) {
          if (amountMode) {
            note.textContent = openBills.length
              ? 'This amount starts at the oldest open bill. Anything above that month is forwarded to the next month.'
              : 'This amount starts at the next billing period. Anything above that month is forwarded to the next month.';
          } else if (!ready) {
            note.textContent = 'Advance months stay off until every open bill is included. An advance amount can be posted without selecting every bill.';
          } else if (months > 0 && !boxes.length) {
            note.textContent = 'These months are posted ahead of the next bill, using the current monthly amount.';
          } else if (months > 0) {
            note.textContent = 'These advance months are posted after the open bills, using the current monthly amount.';
          } else if (!boxes.length) {
            note.textContent = 'Enter an advance amount, or choose how many months to post ahead.';
          } else {
            note.textContent = 'Enter an advance amount, or add advance months after these bills.';
          }
        }
        if (totalNode) {
          totalNode.textContent = selectedTotal(cash, months).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
      }
      boxes.forEach(function (box, index) {
        box.addEventListener('change', function () {
          if (box.checked) {
            for (var earlier = 0; earlier < index; earlier++) boxes[earlier].checked = true;
          } else {
            for (var later = index; later < boxes.length; later++) boxes[later].checked = false;
          }
          syncPayDesk();
        });
      });
      if (advance) advance.addEventListener('input', syncPayDesk);
      if (amountInput) amountInput.addEventListener('input', syncPayDesk);
      payForm.addEventListener('submit', function (event) {
        if (allowSubmit) {
          allowSubmit = false;
          return;
        }
        event.preventDefault();
        var cash = amountCents();
        var months = selectedMonths();
        var count = boxes.filter(function (box) { return box.checked && !box.disabled; }).length;
        if (cash < 1 && count < 1 && months < 1) {
          window.alert('Select the oldest open bill, enter an advance amount, or enter at least one advance month.');
          return;
        }
        var lines = cash > 0 ? amountLines(cash) : monthLines(months);
        if (!lines.length) {
          window.alert('Nothing to post yet. Check the selected bills, advance amount, or advance months.');
          return;
        }
        var total = selectedTotal(cash, months);
        var summary = '<strong>' + subscriberName + '</strong> · ' + mediumLabel()
          + ' · total ₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (cash > 0) {
          summary += '<br>Advance amount ₱' + money(cash) + ' applied from the oldest bill forward.';
        } else {
          summary += '<br>' + count + ' open bill' + (count === 1 ? '' : 's');
          if (months > 0) summary += ' and ' + months + ' advance month' + (months === 1 ? '' : 's');
          summary += '.';
        }
        if (confirmSummary) confirmSummary.innerHTML = summary;
        if (confirmMonths) confirmMonths.innerHTML = lines.map(function (line) { return '<li>' + line + '</li>'; }).join('');
        if (confirmModal.length) confirmModal.modal('show');
      });
      if (confirmSubmit) {
        confirmSubmit.addEventListener('click', function () {
          allowSubmit = true;
          if (confirmModal.length) confirmModal.modal('hide');
          payForm.submit();
        });
      }
      syncPayDesk();
    }
    $('#example2').DataTable({
      "paging": true,
      "lengthChange": false,
      "searching": false,
      "ordering": true,
      "info": true,
      "autoWidth": false,
      "responsive": true,
    });
  });
</script>
</body>
</html>
