<?php
require_once __DIR__ . '/../../includes/guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | General Form Elements</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../../plugins/fontawesome-free/css/all.min.css">
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
      <span class="brand-text font-weight-light">AdminLTE 3</span>
    </a>

    <?php include('../../navigation.sub.php')?>


      <!-- SidebarSearch Form -->
      <div class="form-inline">
        <div class="input-group" data-widget="sidebar-search">
          <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
          <div class="input-group-append">
            <button class="btn btn-sidebar">
              <i class="fas fa-search fa-fw"></i>
            </button>
          </div>
        </div>
      </div>

   
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Plan Update Module</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Payment Module</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-md-6">
            <!-- general form elements -->
        
            <!-- /.card -->

            <!-- general form elements -->
      
            <!-- /.card -->

            <!-- /.card -->


                               <?php
                                  $stateofAdvance='1';
                                   $subID=$_GET['uid'];
                                    include('../../dbconnect.php');
                                    $sql = "SELECT * FROM tbl_subs_profile as a,tbl_plans as b where a.id='$subID' and plan_id=b.id";
                                    $result = $conn->query($sql);
                                    while($row = $result->fetch_assoc()) {
                                       $s_name=$row['s_name'];
                                       $s_address=$row['s_address'];
                                       $s_plan=$row['p_name'].' ('.$row['p_speed_kb']. 'kb speed) - Php'.$row['p_monthly_amount'];
                                      
                                      $d=date_create($s_doi=$row['s_doi']);
                                      $s_doi= date_format($d,"F d, Y");
                                    } 
                                      $sql1 = "SELECT * FROM tbl_payments where payment_uid='$subID' and status='1' order by id asc";  
                                    $result1 = $conn->query($sql1);
                                    if ($result1->num_rows > 0) {
                                      $stateofAdvance='1';
                                    while($row1 = $result1->fetch_assoc()) {
                                        $pDay=$row1['payment_day'];
                                       $p_month=$row1['payment_month'].' - '.$row1['payment_day'];
                                       $due=$row1['payment_amount'];
                                       $pd=date_create($row1['payment_month']);
                                       $pdatee=date_create($row1['payment_date']);
                                       $pdate= date_format($pdatee,"F d, Y");
                                       $pdateFormatted= date_format($pd,"Y-m-d");

                                       $startDate = $pdateFormatted; // select date in Y-m-d format
                                       $nMonths = 1; // choose how many months you want to move ahead
                                       $final = endCycle($startDate, $nMonths); // output: 2014-07-02
                                       $pdd=date_create($final);
                                       $finall= date_format($pdd,"F");
                                      }
                                    }
                                      else{
                                        $stateofAdvance='0';
                                        $p_month='------------';
                                        $due='-----';
                                        $pdate= '----------------------';
                                        $finall= '------------';
                                      }
  
                                     

                                             function add_months($months, DateTime $dateObject) 
                                                   {
                                                         $next = new DateTime($dateObject->format('Y-m-d'));
                                                         $next->modify('last day of +'.$months.' month');
                                                 
                                                         if($dateObject->format('d') > $next->format('d')) {
                                                             return $dateObject->diff($next);
                                                         } else {
                                                             return new DateInterval('P'.$months.'M');
                                                         }
                                                     }
                                               function endCycle($d1, $months)
                                                         {
                                                             $date = new DateTime($d1);

                                                             // call second function to add the months
                                                             $newDate = $date->add(add_months($months, $date));

                                                             // goes back 1 day from date, remove if you want same day of month
                                                             $newDate->sub(new DateInterval('P1D')); 

                                                             //formats final date to Y-m-d form
                                                             $dateReturned = $newDate->format('Y-m-d'); 

                                          return $dateReturned;
                                                         }     


                                                         
                                    
                                    $conn->close();
                                      ?>
            <!-- Horizontal Form -->
            <div class="card card-info">
              <div class="card-header">
                <h3 class="card-title">Plan Update Module</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form class="form-horizontal" method='post' action='update_plan_code.php'>
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(shaven_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-body">
                  
                <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label"> Name</label>
                    <div class="col-sm-10">
                      <input type="text" value="<?php echo $s_name; ?>" class="form-control" id="inputEmail3" name="s_address">
                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label"> Address</label>
                    <div class="col-sm-10">
                  <input type="text" value="<?php echo $s_address; ?>"  class="form-control" id="inputEmail3" name="s_address" placeholder="Address">
                    </div>
                  </div>
                 
          
         
                  <div class="form-group row">
                  <label for="inputEmail3" class="col-sm-2 col-form-label">Plan</label>
                  <div class="col-sm-10">
                    <input type="text" value="<?php echo $s_plan; ?>"  class="form-control" id="inputEmail3" name="s_address" placeholder="Address">
                  </div>
                </div>
                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label"> Installed </label>
                    <div class="col-sm-10">
                      <input type="text" value="<?php echo $s_doi; ?>"  class="form-control" id="inputEmail3" name="s_plan" placeholder="Date of Installation">
                    </div>
                  </div>
                  <input type="hidden" value="<?php echo $subID; ?>"  name="sid"> 
                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label">New Plan</label>
                    <div class="col-sm-10">
                    <div class="form-group">
                        <select class="form-control" name="plan_id">

                                    <?php
                                    include('../../dbconnect.php');
                                    $sql = "SELECT * FROM tbl_plans";
                                    $result = $conn->query($sql);
                                    while($row = $result->fetch_assoc()) {
                                      $pid=(int)$row['id'];
                                      $label=htmlspecialchars($row['p_name'], ENT_QUOTES, 'UTF-8').' - '.htmlspecialchars(number_format(((int)$row['p_speed_kb'])/1000, 1), ENT_QUOTES, 'UTF-8').' Mbps - Php '.htmlspecialchars($row['p_monthly_amount'], ENT_QUOTES, 'UTF-8');
                                      echo "<option value='$pid'>$label</option>";
                                    } 
                                    $conn->close();
                                ?>
                          
                         


                        </select>
                      </div>
                    </div>
                  </div>
               
               
                <!-- /.card-body -->
                <div class="card-footer">
                 <button type="submit" class="btn btn-info">Confirm Plan Update</button>
                  
                  <a href="../tables/reports.php" > <button type="button" class="btn btn-default float-right">Cancel</button></a>
                  <a href="../tables/reports.php" ><button type="button" class="btn btn-default float-right">Back</button></a>
                </div>
                <!-- /.card-footer -->
              </form>
            </div>
            <!-- /.card -->

          </div>
          <!--/.col (left) -->
          <!-- right column -->
          <div class="col-md-6">
            <!-- Form Element sizes -->
       
            <!-- /.card -->

            <!-- /.card -->

        
            <!-- /.card -->
            <!-- general form elements disabled -->
       
<!-- ./wrapper -->

<!-- jQuery -->
<script src="../../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- bs-custom-file-input -->
<script src="../../plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<!-- AdminLTE App -->
<script src="../../dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="../../dist/js/demo.js"></script>
<!-- Page specific script -->
<script>
$(function () {
  bsCustomFileInput.init();
});
</script>
</body>
</html>
