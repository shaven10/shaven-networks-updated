<?php
require_once __DIR__ . '/../../includes/guard.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if(!isset($_SESSION['usr_id']))
{
	header("location:login.php");
}
$sessionID=$_SESSION['usr_id'];
$SName=$_SESSION['fullname'];


  //date of today
  $datenow=date_create('Asia/Taipei');
  $DateofToday= date_format($datenow,"m-d-Y");
  $DofToday= date_format($datenow,"d");
  $MonthofToday= date_format($datenow,"F");
  //end
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
            <h1>Collection Report</h1>
            <h5>Collector: <?php echo $SName; ?></h5>
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


                

            <div class="card">
           
              <div class="card-header">
             <!-- hide sa kay gechange into actual collection

              <form action='' method='post'>
              <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    
                    <a href=""><button  type="button" class="btn btn-warning">All Records</button></a>
                    <button  name='filter' type="submit" class="btn btn-info">Filter Records</button>
                  </div>
                 
                  <input type="month" name='d1' class="form-control" required>
                </div>
              </form>

      -->
        
              </div>
              


              <!-- /.card-header -->
              <div class="card-body">
                <table id="example1" class="table table-bordered table-striped" data-shaven-group="off">
                  <thead>
                  <tr>
              
               
                  <th>Payment Timestamp</th>
                    <th>Subscriber Name</th>
                    <th>Plan Availed</th>
                    <th>Monthly(Php)</th>
                    <th>Due date</th>
                    <th>Status</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php
                      include('../../dbconnect.php');

                     


                   echo' <script>
                    function confirmDelete(link) {
                        if (confirm("Confirm Transaction?")) {
                            doAjax(link.href, "POST"); // doAjax needs to send the "confirm" field
                              }
                              return false;
                          }
                      </script>';

                      if (isset($_POST['filter'])) {	
                        $dtpickerD1=$_POST["d1"];
                   
                          $D1=date_create($dtpickerD1);

                                $D11=date_format($D1,"Y");
                               $D12=date_format($D1,"m");

                           $D111=date_format($D1,"F, Y");




                   $sql = "SELECT payment_amount,c.status as stat,payment_date,c.id as pid,c.status as stat,payment_year,c.period_start,c.period_end,c.due_date,payment_day,payment_month,p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b,tbl_payments as c,tbl_remittances as d where d.paymentid=c.id and remit='0' and payment_uid=a.id and a.plan_id=b.id and a.status='1'and c.status='1' and YEAR(payment_date)='$D11' and MONTH(payment_date)='$D12' and postedby='$sessionID'";
                  $statTEXT='<i>Showing records for the month of <font color="green">'.$D111.'</font> ';
                  }else{
                    $sql = "SELECT payment_amount,c.status as stat,payment_date,c.id as pid,c.status as stat,payment_year,c.period_start,c.period_end,c.due_date,payment_day,payment_month,p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b,tbl_payments as c,tbl_remittances as d where d.paymentid=c.id and remit='0' and payment_uid=a.id and a.plan_id=b.id and a.status='1'and c.status='1' and postedby='$sessionID'";
                    $statTEXT='<i>Showing all records from the database';
                  }
                  
               
                 echo' <h5 >'.$statTEXT.'</h5>';
              



                   
                 
                    $result = $conn->query($sql);
                    if ($result->num_rows > 0) {
                      // output data of each row
                      while($row = $result->fetch_assoc()) {

                        $billingSTAT=$row['stat'];
                        if($billingSTAT==0){
                          $date1='N/A';
                        }else{
                          $date=date_create($row['payment_date']);
                          $date1=date_format($date,"m-d-Y ( h:i:a )");
                         // $date1=date_format($date,"F d, Y h:i:s");
                        }

                        
                       // $date=date_create($row['s_doi']);
                        $pid=$row['id'];
                      echo "<tr>
                     
                      <td>".$date1."</td>
                        <td>".$row['s_name']."</td>
                        <td>".$row['p_name'].' ('.$row['p_speed_kb']. 'kb)'."</td>
                        <td>".$row['payment_amount']."</td>
                       
                        ".shaven_due_cell($row)."";

                        if ($row['stat']=='1'){

                          echo '<td><span class="badge badge-success">Paid</span></></td>';

                        }else{
                          echo '<td><span class="badge badge-warning">Unpaid</span></></td>';

                        }
                        echo "</tr>";
                     //   $payment_day=date_format($date,"d");
                    //    $p_amount=$row['p_monthly_amount'];
                        
 

                     
                      }
                    } else {
                     // echo "0 results";
                    }
                  
                    $conn->close();
                     
                  ?> 

                  </tbody>
                  <tfoot>
                  <tr>
             
                  <th>Payment Timestamp</th>
                    <th>Subscriber Name</th>
                    <th>Plan Availed</th>
                    <th>Monthly(Php)</th>
                    <th>Due date</th>
                    <th>Status</th>
                  </tr>
                  </tfoot>
                </table>
              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->
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
<script src="../../dist/js/shaven-groups.js"></script>
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
      "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
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
