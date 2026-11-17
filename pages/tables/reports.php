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
  $YearofToday= date_format($datenow,"Y");
  $MonthofToday= date_format($datenow,"F");
  //end

 // $UID=$_GET['uid'];

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
            <h1>All Registered Subscribers</h1>
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
                <h3 class="card-title w-100">Subscribers, from the earliest installation to the latest</h3>
                <p class="mb-0 text-muted">Loading this page also creates this month's bill for subscribers whose billing day has arrived.</p>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <table id="example1" class="table table-bordered table-striped" data-install-order="0">
                  <thead>
                  <tr>
              
                  <th>Account / Installed</th>
                    <th>Subscriber Name</th>
                    <th>Plan Availed</th>
                    <th>Medium</th>
                    <th>Monthly Payment</th>
                    <th>Status</th>
                  </tr>
                  </thead>
                  <tbody>

                  <?php
                      include('../../dbconnect.php');

                     //date of today
                    $datenow=date_create('Asia/Taipei');
                    $DateofToday= date_format($datenow,"m-d-Y");
                    $DateofToday1= date_format($datenow,"Y-m-d");
                    $DofToday= date_format($datenow,"d");
                    $YearofToday= date_format($datenow,"Y");
                    $MonthofToday= date_format($datenow,"F");
                    //end


                    echo' <script>
                    function confirmDeleteActivate(link) {
                            if (confirm("Activate this subscriber?")) {
                                doAjax(link.href, "POST"); // doAjax needs to send the "confirm" field
                                  }
                                  return false;
                              }
                              function confirmDeleteDeactivate(link) {
                                if (confirm("Deactivate Subscriber?")) {
                                    doAjax(link.href, "POST"); // doAjax needs to send the "confirm" field
                                      }
                                      return false;
                                  }
                                  function confirmDeleteUpdatePlan(link) {
                                    if (confirm("Update Plan Details?")) {
                                        doAjax(link.href, "POST"); // doAjax needs to send the "confirm" field
                                          }
                                          return false;
                                      }
                      </script>';



                    $sql = "SELECT a.account_no, medium_name,a.status as stat,p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b,tbl_mediums as c where medium=c.id and a.plan_id=b.id order by a.s_doi asc, a.s_name asc";
                    $result = $conn->query($sql);
                    if ($result->num_rows > 0) {
                      // output data of each row
                      while($row = $result->fetch_assoc()) {
                        $date=date_create($row['s_doi']);
                         $dofinstallation=date_format($date,"Y-m-d");
                        $pid=$row['id'];

                        //Calculate the date difference based on timestamp values
                        $difference = strtotime($dofinstallation) - strtotime($DateofToday1);

                        //Calculate difference in days
                      $days = (int)abs($difference/(60 * 60)/24);
                     // echo "<br/>The difference between ".$DateofToday1." and ".$dofinstallation." is ".$days." days.";
                     // <td>".$row['id']."</td>
                      

                      echo "<tr>
                    
                     
                      ".shaven_account_cell($row['account_no'], $row['s_doi'])."
                     
                      <td><a href='subscriber_view.php?uid=".(int)$row['id']."'>".htmlspecialchars($row['s_name'], ENT_QUOTES, 'UTF-8')."</a></td>

                       
                        <td><a onclick='return confirmDeleteUpdatePlan(this);' href='../forms/update_plan.php?uid=".$row['id']."'>".$row['p_name'].' ('.$row['p_speed_kb']. 'kb)'."</a></td>
                        <td>".$row['medium_name']."</td>
                        <td>".$row['p_monthly_amount']."</td>";


                         $profileStat=$row['stat'];
                        if($profileStat==2){
                          $stat='Inactive';
                       
                          echo '<td> <a onclick="return confirmDeleteActivate(this);" href="../tables/sub_activate.php?uid='.$row["id"].'"><span class="badge badge-danger">Inactive</span></></td></tr>';
                   
                       //  echo "<td><span class='badge badge-danger'> Inactive </span></td> </tr>";
                        }else if($profileStat==0){
                          $stat='Pending';
                          echo '<td> <a onclick="return confirmDeleteActivate(this);" href="../tables/sub_activate.php?uid='.$row["id"].'"><span class="badge badge-warning">Pending</span></></td></tr>';
                   
                        //  echo "<td><span class='badge badge-warning'> Pending </span></td> </tr>";
                        }
                        else{
                          $stat='Active';
                          echo '<td><a onclick="return confirmDeleteDeactivate(this);" href="../tables/sub_deactivate.php?uid='.$row["id"].'"><span class="badge badge-success"> Active </span></a></td></tr>';
                   
                        }


                      
                              $payment_day=date_format($date,"d");
                              $p_amount=$row['p_monthly_amount'];
                            
                              if ($days>30){

                              if ($DofToday>=$payment_day){   

                                        $sql1 = "SELECT * FROM tbl_payments where payment_year='$YearofToday' and payment_month='$MonthofToday' and payment_uid='$pid'";
                                        $result1 = $conn->query($sql1);
                                        //echo $result1->num_rows;
                                        if ($result1->num_rows > 0) {
                                         
                                          
                                        }else{
                                            //add to colektahonon table
                                            $sql = "INSERT INTO tbl_payments (payment_year,payment_amount,payment_day,payment_month,payment_uid,p_timestamp)
                                            VALUES ('$YearofToday','$p_amount','$payment_day','$MonthofToday','$pid',now());";
                                            if ($conn->multi_query($sql) === TRUE) {
                                              // echo '<script> alert("New records created successfully");</script>'; 
                                              } else {
                                                  echo "Error: " . $sql . "<br>" . $conn->error;
                                                  }
                                            // 
                                        }
                                          
                                
                                            
                              }else{

                                //do nothing
                              }
                            }
                             

                     
                      }
                    } else {
                      echo "0 results";
                    }
                  
                    $conn->close();
                     
                  ?>

                  </tbody>
                  <tfoot>
                  <tr>
                  <th>Account / Installed</th>
                    <th>Subscriber Name</th>
                    <th>Plan Availed</th>
                    <th>Medium</th>
                    <th>Monthly Payment</th>
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
