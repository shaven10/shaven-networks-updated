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
  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="../../index3.html" class="nav-link">Home</a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="#" class="nav-link">Contact</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
          <i class="fas fa-search"></i>
        </a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </li>

      <!-- Messages Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-comments"></i>
          <span class="badge badge-danger navbar-badge">3</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <a href="#" class="dropdown-item">
            <!-- Message Start -->
            <div class="media">
              <img src="../../dist/img/user1-128x128.jpg" alt="User Avatar" class="img-size-50 mr-3 img-circle">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  Brad Diesel
                  <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                </h3>
                <p class="text-sm">Call me whenever you can...</p>
                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
              </div>
            </div>
            <!-- Message End -->
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <!-- Message Start -->
            <div class="media">
              <img src="../../dist/img/user8-128x128.jpg" alt="User Avatar" class="img-size-50 img-circle mr-3">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  John Pierce
                  <span class="float-right text-sm text-muted"><i class="fas fa-star"></i></span>
                </h3>
                <p class="text-sm">I got your message bro</p>
                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
              </div>
            </div>
            <!-- Message End -->
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <!-- Message Start -->
            <div class="media">
              <img src="../../dist/img/user3-128x128.jpg" alt="User Avatar" class="img-size-50 img-circle mr-3">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  Nora Silvester
                  <span class="float-right text-sm text-warning"><i class="fas fa-star"></i></span>
                </h3>
                <p class="text-sm">The subject goes here</p>
                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
              </div>
            </div>
            <!-- Message End -->
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
        </div>
      </li>
      <!-- Notifications Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <span class="badge badge-warning navbar-badge">15</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">15 Notifications</span>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-envelope mr-2"></i> 4 new messages
            <span class="float-right text-muted text-sm">3 mins</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-users mr-2"></i> 8 friend requests
            <span class="float-right text-muted text-sm">12 hours</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-file mr-2"></i> 3 new reports
            <span class="float-right text-muted text-sm">2 days</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
          <i class="fas fa-th-large"></i>
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="../../index3.html" class="brand-link">
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
                                     echo  $p_name=$row['p_name'];
                                      $pid=$row['id'];
                                      echo "<option value='$pid'>$p_name</option>";
                                    } 
                                    $conn->close();
                                ?>
                          
                         


                        </select>
                      </div>
                    </div>
                  </div>
               
               
                <!-- /.card-body -->
                <div class="card-footer">
                 <?php 
                 if ( $stateofAdvance==0){
                    echo'<button disabled type="submit" class="btn btn-info">Confirm Plan Update</button>';
                  }else{
                    echo'<button type="submit" class="btn btn-info">Confirm Plan Update</button>';
                  }?>
                  
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
