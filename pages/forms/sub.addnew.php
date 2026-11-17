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
      <span class="brand-text font-weight-light">SHAVEN Networks</span>
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
            <h1>Registration Form New Subscriber</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Registration Form</li>
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
            <!-- Horizontal Form -->
            <div class="card card-info">
              <div class="card-header">
                <h3 class="card-title">New Subscriber Registration Module</h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form class="form-horizontal" method='post' action='addsub.php'>
                <div class="card-body">
                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label"> Name</label>
                    <div class="col-sm-10">
                      <input type="text" class="form-control" id="inputEmail3" name="s_name" placeholder="Name">
                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label"> Address</label>
                    <div class="col-sm-10">
                      <textarea name="s_address" class="form-control" rows="3" placeholder="Address"></textarea>
                    </div>
                  </div>
                 
                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label">Medium</label>
                    <div class="col-sm-10">
                    <div class="form-group">
                        <select class="form-control" name="medium">

                                    <?php
                                    include('../../dbconnect.php');
                                    $sql = "SELECT * FROM tbl_mediums";
                                    $result = $conn->query($sql);
                                    while($row = $result->fetch_assoc()) {
                                       $m_name=$row['medium_name'];
                                      $mid=$row['id'];
                                      echo "<option value='$mid'>$m_name</option>";
                                    } 
                                    $conn->close();
                                ?>
                          
                         


                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label">Plan</label>
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
                  <div class="form-group row">
                    <label for="inputEmail3" class="col-sm-2 col-form-label"> Date</label>
                    <div class="col-sm-10">
                      <input type="date" class="form-control" id="inputEmail3" name="s_doi" placeholder="Date of Installation">
                    </div>
                  </div>


                </div>
                <!-- /.card-body -->
                <div class="card-footer">
                  <button type="submit" class="btn btn-info">Register</button>
                  <button type="submit" class="btn btn-default float-right">Cancel</button>
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
