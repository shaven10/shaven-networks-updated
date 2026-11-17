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

//  $UID=$_GET['uid'];

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

    <?php include('../../navigation.sub.php')?>
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
              <li class="breadcrumb-item active">Manual Entry of Collectibles</li>
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
                <h3 class="card-title">Information: Manual entry for previous collectible payments.</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <table id="example1" class="table table-bordered table-striped" data-install-order="0">
                  <thead>
                  <tr>
                 
                  <th >Date of Installation</th>
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
                    $MonthofToday= date_format($datenow,"F");
                    //end


                    echo' <script>
                    function confirmDelete(link) {
                        if (confirm("Proceed to manual entry of collectibles module?")) {
                            doAjax(link.href, "POST"); // doAjax needs to send the "confirm" field
                              }
                              return false;
                          }
                      </script>';



                    $sql = "SELECT medium_name,a.status as stat,p_speed_kb,s_name,p_name,s_doi,p_monthly_amount,a.id as id FROM tbl_subs_profile as a,tbl_plans as b,tbl_mediums as c where medium=c.id and a.plan_id=b.id and a.status='1' order by a.s_doi asc, a.s_name asc";
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
                 
                     
                      ".shaven_install_cell($row['s_doi'])."
                        <td>".$row['s_name']."</td>
                        <td>".$row['p_name'].' ('.$row['p_speed_kb']. 'kb)'."</td>
                        <td>".$row['medium_name']."</td>
                        <td>".$row['p_monthly_amount']."</td>";


                         $profileStat=$row['stat'];
                        if($profileStat==2){
                          $stat='Inactive';
                          echo "<td><span class='badge badge-danger'> Inactive </span></td> </tr>";
                        }else if($profileStat==0){
                          $stat='Pending';
                          echo "<td><span class='badge badge-warning'> Pending </span></td> </tr>";
                        }
                        else{
                          $stat='Active';
                          echo '<td><span class="badge badge-success"> Active </span> <a onclick="return confirmDelete(this);" href="../tables/manual_entry_collect.php?uid='.$row["id"].'"><span class="badge badge-info">Go to manual entry?</span></></td></tr>';
                   
                        }


                      
                              $payment_day=date_format($date,"d");
                              $p_amount=$row['p_monthly_amount'];
                            /*
                              if ($days>30){

                              if ($DofToday>=$payment_day){   

                                        $sql1 = "SELECT * FROM tbl_payments where payment_month='$MonthofToday' and payment_uid='$pid'";
                                        $result1 = $conn->query($sql1);
                                        //echo $result1->num_rows;
                                        if ($result1->num_rows > 0) {
                                         
                                          
                                        }else{
                                            //add to colektahonon table
                                            $sql = "INSERT INTO tbl_payments (payment_amount,payment_day,payment_month,payment_uid,p_timestamp)
                                            VALUES ('$p_amount','$payment_day','$MonthofToday','$pid',now());";
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

                            */
                             

                     
                      }
                    } else {
                      echo "0 results";
                    }
                  
                    $conn->close();
                     
                  ?>

                  </tbody>
                  <tfoot>
                  <tr>
                
                  <th >Date of Installation</th>
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
